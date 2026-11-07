<?php

namespace App\Providers;

use App\Mail\GmailApiTransport;
use Illuminate\Support\Facades\Mail;
use Illuminate\Support\ServiceProvider;
use RuntimeException;

class GmailApiServiceProvider extends ServiceProvider
{
    public function register(): void
    {
        //
    }

    public function boot(): void
    {
        Mail::extend('gmail_api', function (array $config) {
            foreach (['client_id', 'client_secret', 'refresh_token'] as $key) {
                if (empty($config[$key])) {
                    throw new RuntimeException(
                        "Missing Gmail API configuration: {$key}"
                    );
                }
            }

            return new GmailApiTransport(
                clientId: $config['client_id'],
                clientSecret: $config['client_secret'],
                refreshToken: $config['refresh_token'],
            );
        });
    }
}
