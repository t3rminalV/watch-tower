<?php

use App\Http\Controllers\DashboardController;
use App\Http\Controllers\Project\CacheController;
use App\Http\Controllers\Project\CommandsController;
use App\Http\Controllers\Project\ExceptionsController;
use App\Http\Controllers\Project\IssuesController;
use App\Http\Controllers\Project\JobsController;
use App\Http\Controllers\Project\LogsController;
use App\Http\Controllers\Project\MailController;
use App\Http\Controllers\Project\NotificationsController;
use App\Http\Controllers\Project\OutgoingRequestsController;
use App\Http\Controllers\Project\QueriesController;
use App\Http\Controllers\Project\RequestsController;
use App\Http\Controllers\Project\ScheduledTasksController;
use App\Http\Controllers\Project\UsersController;
use Illuminate\Support\Facades\Route;

Route::get('/', [DashboardController::class, 'home'])->name('home');

Route::scopeBindings()
    ->prefix('projects/{project:slug}')
    ->name('projects.')
    ->group(function () {
        Route::redirect('/', '/projects/{project:slug}/dashboard');
        Route::get('/dashboard', [DashboardController::class, 'show'])->name('dashboard');
        Route::get('/requests', [RequestsController::class, 'index'])->name('requests.index');
        Route::get('/exceptions', [ExceptionsController::class, 'index'])->name('exceptions.index');
        Route::get('/exceptions/{exception}', [ExceptionsController::class, 'show'])
            ->where('exception', '[0-9a-fA-F\-]{36}')
            ->name('exceptions.show');
        Route::get('/issues', [IssuesController::class, 'index'])->name('issues.index');
        Route::get('/issues/{issue}', [IssuesController::class, 'show'])
            ->whereNumber('issue')
            ->name('issues.show');
        Route::patch('/issues/{issue}', [IssuesController::class, 'update'])
            ->whereNumber('issue')
            ->name('issues.update');
        Route::get('/jobs', [JobsController::class, 'index'])->name('jobs.index');
        Route::get('/jobs/{job}', [JobsController::class, 'show'])
            ->where('job', '[A-Za-z0-9._-]+')
            ->name('jobs.show');
        Route::get('/commands', [CommandsController::class, 'index'])->name('commands.index');
        Route::get('/commands/{command}', [CommandsController::class, 'show'])
            ->where('command', '[A-Za-z0-9._:-]+')
            ->name('commands.show');
        Route::get('/scheduled-tasks', [ScheduledTasksController::class, 'index'])->name('scheduled-tasks.index');
        Route::get('/scheduled-tasks/{task}', [ScheduledTasksController::class, 'show'])
            ->where('task', '[A-Fa-f0-9]{40}')
            ->name('scheduled-tasks.show');
        Route::get('/queries', [QueriesController::class, 'index'])->name('queries.index');
        Route::get('/queries/{query}', [QueriesController::class, 'show'])
            ->where('query', '[A-Fa-f0-9]{40}')
            ->name('queries.show');
        Route::get('/mail', [MailController::class, 'index'])->name('mail.index');
        Route::get('/mail/{mail}', [MailController::class, 'show'])
            ->where('mail', '[A-Fa-f0-9]{40}')
            ->name('mail.show');
        Route::get('/notifications', [NotificationsController::class, 'index'])->name('notifications.index');
        Route::get('/notifications/{notification}', [NotificationsController::class, 'show'])
            ->where('notification', '[A-Fa-f0-9]{40}')
            ->name('notifications.show');
        Route::get('/cache', [CacheController::class, 'index'])->name('cache.index');
        Route::get('/http-client', [OutgoingRequestsController::class, 'index'])->name('http-client.index');
        Route::get('/http-client/{domain}', [OutgoingRequestsController::class, 'show'])
            ->where('domain', '[A-Fa-f0-9]{40}')
            ->name('http-client.show');
        Route::get('/logs', [LogsController::class, 'index'])->name('logs.index');
        Route::get('/users', [UsersController::class, 'index'])->name('users.index');
        Route::get('/users/{user}', [UsersController::class, 'show'])
            ->where('user', '[A-Za-z0-9_-]+')
            ->name('users.show');
    });
