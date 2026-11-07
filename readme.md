# to fix broken storage link
rm -rf public/storage     # Remove the broken/old storage link
php artisan storage:link  # Create fresh symbolic link



copy .env.example .env