<?php

namespace App\Providers;

use Illuminate\Support\Facades\Route;
use Illuminate\Support\ServiceProvider;
use Library\Adapters\Persistence\PostgresBookRepository;
use Library\Adapters\Persistence\PostgresCategoryRepository;
use Library\Adapters\Persistence\PostgresLoanRepository;
use Library\Adapters\Persistence\PostgresMemberRepository;
use Library\Adapters\Persistence\PostgresUserRepository;
use Library\Adapters\Security\BcryptPasswordHasher;
use Library\Adapters\Security\JwtTokenIssuer;
use Library\Adapters\Storage\DiskCoverStorage;
use Library\Application\Ports\Outbound\BookRepository;
use Library\Application\Ports\Outbound\CategoryRepository;
use Library\Application\Ports\Outbound\CoverStorage;
use Library\Application\Ports\Outbound\LoanRepository;
use Library\Application\Ports\Outbound\MemberRepository;
use Library\Application\Ports\Outbound\PasswordHasher;
use Library\Application\Ports\Outbound\TokenIssuer;
use Library\Application\Ports\Outbound\UserRepository;
use Library\Application\Services\CreateLibrarianService;
use Library\Application\Services\LoginService;

class AppServiceProvider extends ServiceProvider
{
    /**
     * Register any application services.
     */
    public function register(): void
    {
        // Repositories
        $this->app->singleton(UserRepository::class, PostgresUserRepository::class);
        $this->app->singleton(BookRepository::class, PostgresBookRepository::class);
        $this->app->singleton(MemberRepository::class, PostgresMemberRepository::class);
        $this->app->singleton(LoanRepository::class, PostgresLoanRepository::class);
        $this->app->singleton(CategoryRepository::class, PostgresCategoryRepository::class);

        // Security
        $this->app->singleton(PasswordHasher::class, BcryptPasswordHasher::class);
        $this->app->singleton(TokenIssuer::class, function () {
            return new JwtTokenIssuer(config('app.jwt_secret', env('JWT_SECRET', 'default-secret')));
        });

        // Storage
        $this->app->singleton(CoverStorage::class, DiskCoverStorage::class);

        // Services
        $this->app->singleton(LoginService::class);
        $this->app->singleton(CreateLibrarianService::class);
    }

    /**
     * Bootstrap any application services.
     */
    public function boot(): void
    {
        // Register JWT middleware
        Route::aliasMiddleware('auth.jwt', \App\Http\Middleware\JwtAuthMiddleware::class);

        // Create initial admin user if not exists
        $this->createInitialAdmin();
    }

    private function createInitialAdmin(): void
    {
        if (!app()->runningInConsole() || app()->environment('testing')) {
            return;
        }

        try {
            $username = env('INITIAL_ADMIN_USERNAME');
            $password = env('INITIAL_ADMIN_PASSWORD');

            if (!$username || !$password) {
                return;
            }

            $userRepo = app(UserRepository::class);
            $existing = $userRepo->findByUsername($username);

            if ($existing) {
                return;
            }

            $hasher = app(PasswordHasher::class);
            $user = new \Library\Domain\User(
                $userRepo->nextIdentity(),
                $username,
                $hasher->hash($password),
                'admin'
            );

            $userRepo->save($user);
        } catch (\Exception $e) {
            // Silent fail - DB might not be ready
        }
    }
}
