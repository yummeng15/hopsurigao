
param(
    [switch]$Quick
)

$ErrorActionPreference = 'Stop'
Set-StrictMode -Version Latest

$project = (Resolve-Path (Join-Path $PSScriptRoot '..')).Path
$results = [System.Collections.Generic.List[string]]::new()
$failures = [System.Collections.Generic.List[string]]::new()

function Get-EnvValue {
    param([string]$Path, [string]$Key)

    if (-not (Test-Path $Path)) {
        throw "Missing configuration file: $Path"
    }

    $pattern = '^\s*' + [regex]::Escape($Key) + '\s*='

    $line = Get-Content $Path |
        Where-Object { $_ -match $pattern } |
        Select-Object -Last 1

    if ($null -eq $line) {
        return ''
    }

    return $line.Substring(
        $line.IndexOf('=') + 1
    ).Trim().Trim('"').Trim("'")
}

function Invoke-Audit {
    param(
        [string]$Label,
        [string]$Program,
        [string[]]$Arguments
    )

    Write-Host "`n=== $Label ===" -ForegroundColor Cyan

    & $Program @Arguments

    $exitCode = $LASTEXITCODE

    if ($exitCode -eq 0) {
        Write-Host "PASS: $Label" -ForegroundColor Green
        $script:results.Add("PASS | $Label")
    }
    else {
        Write-Host "FAIL: $Label (exit $exitCode)" -ForegroundColor Red
        $script:results.Add(
            "FAIL | $Label | exit $exitCode"
        )
        $script:failures.Add($Label)
    }
}

function Invoke-TestFile {
    param(
        [string]$Label,
        [string]$RelativePath
    )

    if (-not (Test-Path $RelativePath)) {
        Write-Host "MISSING: $RelativePath" -ForegroundColor Red

        $script:results.Add(
            "MISSING | $Label | $RelativePath"
        )

        $script:failures.Add("$Label (missing file)")
        return
    }

    Invoke-Audit `
        -Label $Label `
        -Program 'php' `
        -Arguments @('artisan', 'test', $RelativePath)
}

Push-Location $project

try {
    $testingEnv = Join-Path $project '.env.testing'
    $liveEnv = Join-Path $project '.env'

    $databaseName = Get-EnvValue $testingEnv 'DB_DATABASE'
    $connection = Get-EnvValue $testingEnv 'DB_CONNECTION'
    $appEnvironment = Get-EnvValue $testingEnv 'APP_ENV'
    $testDatabaseUrl = Get-EnvValue $testingEnv 'DB_URL'
    $liveDatabaseName = Get-EnvValue $liveEnv 'DB_DATABASE'

    # Refuse to run against an unsafe database.
    if (
        $databaseName -cne 'island_hopping_test' -or
        $connection -cne 'mysql'
    ) {
        throw 'SAFETY STOP: Use DB_CONNECTION=mysql and DB_DATABASE=island_hopping_test in .env.testing.'
    }

    if ($appEnvironment -cne 'testing') {
        throw 'SAFETY STOP: Set APP_ENV=testing in .env.testing.'
    }

    if ($databaseName -eq $liveDatabaseName) {
        throw 'SAFETY STOP: Testing and main databases are the same.'
    }

    if ($testDatabaseUrl -or $env:DB_URL) {
        throw 'SAFETY STOP: Remove DB_URL overrides before testing.'
    }

    if (
        $env:DB_DATABASE -and
        $env:DB_DATABASE -ne 'island_hopping_test'
    ) {
        throw 'SAFETY STOP: Your terminal overrides the test database.'
    }

    Write-Host 'Safe testing database: island_hopping_test' -ForegroundColor Green

    # ROUTES
    Invoke-Audit -Label 'Operator Trip routes' `
        -Program 'php' `
        -Arguments @('artisan', 'route:list', '--name=operator.trips')

    Invoke-Audit -Label 'Operator core routes' `
        -Program 'php' `
        -Arguments @('artisan', 'route:list', '--name=operator.')

    Invoke-Audit -Label 'Admin assignment routes' `
        -Program 'php' `
        -Arguments @('artisan', 'route:list', '--name=superadmin.operator-admins')

    Invoke-Audit -Label 'Tourist routes' `
        -Program 'php' `
        -Arguments @('artisan', 'route:list', '--name=user.')

    # FRONTEND
    Invoke-Audit -Label 'Vue / TypeScript checks' `
        -Program 'npm.cmd' `
        -Arguments @('run', 'types:check')

    Invoke-Audit -Label 'Production frontend build' `
        -Program 'npm.cmd' `
        -Arguments @('run', 'build')

    # STEPS 1-5
    $newTests = @(
        @(
            'Step 1 - Trip completion and no-shows',
            'tests/Feature/Operator/OperatorTripWorkflowTest.php'
        ),
        @(
            'Step 2 - Trip outcome visibility',
            'tests/Feature/TripOutcomeVisibilityTest.php'
        ),
        @(
            'Step 3 - Staff navigation',
            'tests/Feature/Operator/StaffNavigationIntegrityTest.php'
        ),
        @(
            'Step 4 - Financial totals',
            'tests/Feature/Superadmin/DashboardFinancialAccuracyTest.php'
        ),
        @(
            'Step 5 - Admin assignment',
            'tests/Feature/Superadmin/OperatorAdminAssignmentTest.php'
        )
    )

    foreach ($entry in $newTests) {
        Invoke-TestFile $entry[0] $entry[1]
    }

    # EXISTING IMPORTANT FUNCTIONAL TESTS
    $coreTests = @(
        @(
            'Portal separation',
            'tests/Feature/Operator/PortalSeparationTest.php'
        ),
        @(
            'Operator data isolation',
            'tests/Feature/Operator/OperatorOperationsIsolationTest.php'
        ),
        @(
            'Operator payments',
            'tests/Feature/Operator/OperatorPaymentTest.php'
        ),
        @(
            'Operator walk-in and QR',
            'tests/Feature/Operator/OperatorWalkInQrTest.php'
        ),
        @(
            'Tourist booking availability',
            'tests/Feature/User/BookingAvailabilityProtectionTest.php'
        ),
        @(
            'Tourist cancellation',
            'tests/Feature/User/BookingCancellationSafetyTest.php'
        ),
        @(
            'Saved booking prices',
            'tests/Feature/BookingPriceSnapshotTest.php'
        ),
        @(
            'PayMongo checkout and webhooks',
            'tests/Feature/PayMongoCheckoutTest.php'
        ),
        @(
            'Weather restrictions',
            'tests/Feature/WeatherBookingBlockTest.php'
        ),
        @(
            'Payment audit',
            'tests/Feature/BookingPaymentAuditTest.php'
        ),
        @(
            'Super Admin access',
            'tests/Feature/Superadmin/SuperAdminNavigationAccessTest.php'
        )
    )

    foreach ($entry in $coreTests) {
        Invoke-TestFile $entry[0] $entry[1]
    }

    # FULL SUITE (unless Quick was selected).
    if (-not $Quick) {
        Invoke-Audit -Label 'COMPLETE Laravel test suite' `
            -Program 'php' `
            -Arguments @('artisan', 'test')
    }

    # SAVE SUMMARY REPORT
    $reportPath = Join-Path $project 'storage\logs\final-integration-audit.txt'

    $summary = @(
        'HopSurigao Final Integration Audit',
        'Test database: island_hopping_test',
        "Mode: $(if ($Quick) { 'Quick' } else { 'Full' })",
        ''
    ) + @($results.ToArray()) + @(
        '',
        "Failures: $($failures.Count)"
    )

    $summary | Set-Content -Path $reportPath -Encoding UTF8

    Write-Host "`nReport saved to: $reportPath" -ForegroundColor Cyan

    if ($failures.Count -eq 0) {
        Write-Host 'ALL AUTOMATED CHECKS PASSED' -ForegroundColor Green
        Write-Host 'Browser and PayMongo sandbox tests are still required.'
    }
    else {
        Write-Host "$($failures.Count) checks need review." -ForegroundColor Yellow

        foreach ($failure in $failures) {
            Write-Host " - $failure" -ForegroundColor Yellow
        }

        exit 1
    }
}
finally {
    Pop-Location
}
