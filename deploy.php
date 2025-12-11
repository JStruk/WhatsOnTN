<?php
namespace Deployer;

require 'recipe/laravel.php';

// Config

// Project name
set('application', 'WhatsOnTN');

set('repository', 'https://github.com/JStruk/WhatsOnTN');

add('shared_files', ['.env']);
add('shared_dirs', ['storage']);
add('writable_dirs', ['bootstrap/cache', 'storage']);
set('allow_anonymous_stats', false);
// Keep only last 5 releases
set('keep_releases', 5);

// Hosts
host('production')
    ->setHostname('whatsontn.jstruk.com') // or your droplet IP if DNS isn't ready
    ->set('remote_user', 'deployer')
    ->set('deploy_path', '/var/www/whatsontn');

// Tasks
task('npm:install', function () {
    run('cd {{release_path}} && npm install');
});

task('npm:build', function () {
    run('cd {{release_path}} && npm run build');
});

task('cron:install', function () {
    $cron = "* * * * * php {{deploy_path}}/current/artisan schedule:run >> /dev/null 2>&1";
    run("echo '$cron' | crontab -");
});

after('deploy:symlink', 'cron:install');

task('queue:restart', function () {
    run('php {{deploy_path}}/current/artisan queue:restart');
})->desc('Signal queue workers to restart');

after('deploy:symlink', 'queue:restart');

after('deploy:vendors', 'npm:install'); // runs `composer install`
after('npm:install', 'npm:build');

// Hooks
after('deploy:failed', 'deploy:unlock');
