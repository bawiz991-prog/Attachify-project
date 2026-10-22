<p align="center"><a href="https://laravel.com" target="_blank"><img src="https://raw.githubusercontent.com/laravel/art/master/logo-lockup/5%20SVG/2%20CMYK/1%20Full%20Color/laravel-logolockup-cmyk-red.svg" width="400" alt="Laravel Logo"></a></p>

<p align="center">
<a href="https://github.com/laravel/framework/actions"><img src="https://github.com/laravel/framework/workflows/tests/badge.svg" alt="Build Status"></a>
<a href="https://packagist.org/packages/laravel/framework"><img src="https://img.shields.io/packagist/dt/laravel/framework" alt="Total Downloads"></a>
<a href="https://packagist.org/packages/laravel/framework"><img src="https://img.shields.io/packagist/v/laravel/framework" alt="Latest Stable Version"></a>
<a href="https://packagist.org/packages/laravel/framework"><img src="https://img.shields.io/packagist/l/laravel/framework" alt="License"></a>
</p>

## Running Attachify with XAMPP

The project's Laravel migrations are in `database/migrations`. To create the
tables in your local XAMPP MySQL database:

1. Start **MySQL** in the XAMPP Control Panel. Start **Apache** too if you plan
   to serve the app through XAMPP.
2. In phpMyAdmin (`http://localhost/phpmyadmin`), create an empty database
   named `laravelauth`.
3. From the project folder, install dependencies and create your local
   environment file:

   ```powershell
   composer install
   if (-not (Test-Path .env)) { Copy-Item .env.example .env }
   ```

   If you already have a `.env` file, keep it and check that its database
   settings point to XAMPP:

   ```dotenv
   DB_CONNECTION=mysql
   DB_HOST=127.0.0.1
   DB_PORT=3306
   DB_DATABASE=laravelauth
   DB_USERNAME=root
   DB_PASSWORD=
   ```

   Change `DB_DATABASE`, `DB_USERNAME`, or `DB_PASSWORD` if your local MySQL
   settings are different.
4. Generate the app key and run the migrations:

   ```powershell
   php artisan key:generate
   php artisan migrate
   ```

   If `php` is not on your PATH, use XAMPP's PHP executable instead:
   `& C:\xampp\php\php.exe artisan migrate` (and the same executable for other
   Artisan commands).
   To create the first administrator, register an account normally, then
   promote it from PowerShell with `php artisan attachify:make-admin
   admin@example.com`. Confirm the prompt. Administrators approve student and
   supervisor accounts and check both placement PDFs before daily reporting
   starts.
5. Install Node.js LTS (which includes npm), then build the frontend assets:

   ```powershell
   npm install
   npm run build
   ```

   If you install Node.js while PowerShell is open, open a new terminal so
   `node` and `npm` are available on PATH.

To use XAMPP Apache, configure the site's document root to this project's
`public` folder. Alternatively, run `php artisan serve` from the project
folder. Do not use `php artisan migrate:fresh` on a database with data; it
drops existing tables.

## About Laravel

Laravel is a web application framework with expressive, elegant syntax. We believe development must be an enjoyable and creative experience to be truly fulfilling. Laravel takes the pain out of development by easing common tasks used in many web projects, such as:

- [Simple, fast routing engine](https://laravel.com/docs/routing).
- [Powerful dependency injection container](https://laravel.com/docs/container).
- Multiple back-ends for [session](https://laravel.com/docs/session) and [cache](https://laravel.com/docs/cache) storage.
- Expressive, intuitive [database ORM](https://laravel.com/docs/eloquent).
- Database agnostic [schema migrations](https://laravel.com/docs/migrations).
- [Robust background job processing](https://laravel.com/docs/queues).
- [Real-time event broadcasting](https://laravel.com/docs/broadcasting).

Laravel is accessible, powerful, and provides tools required for large, robust applications.

## Learning Laravel

Laravel has the most extensive and thorough [documentation](https://laravel.com/docs) and video tutorial library of all modern web application frameworks, making it a breeze to get started with the framework. You can also check out [Laravel Learn](https://laravel.com/learn), where you will be guided through building a modern Laravel application.

If you don't feel like reading, [Laracasts](https://laracasts.com) can help. Laracasts contains thousands of video tutorials on a range of topics including Laravel, modern PHP, unit testing, and JavaScript. Boost your skills by digging into our comprehensive video library.

## Laravel Sponsors

We would like to extend our thanks to the following sponsors for funding Laravel development. If you are interested in becoming a sponsor, please visit the [Laravel Partners program](https://partners.laravel.com).

### Premium Partners

- **[Vehikl](https://vehikl.com)**
- **[Tighten Co.](https://tighten.co)**
- **[Kirschbaum Development Group](https://kirschbaumdevelopment.com)**
- **[64 Robots](https://64robots.com)**
- **[Curotec](https://www.curotec.com/services/technologies/laravel)**
- **[DevSquad](https://devsquad.com/hire-laravel-developers)**
- **[Redberry](https://redberry.international/laravel-development)**
- **[Active Logic](https://activelogic.com)**

## Contributing

Thank you for considering contributing to the Laravel framework! The contribution guide can be found in the [Laravel documentation](https://laravel.com/docs/contributions).

## Code of Conduct

In order to ensure that the Laravel community is welcoming to all, please review and abide by the [Code of Conduct](https://laravel.com/docs/contributions#code-of-conduct).

## Security Vulnerabilities

If you discover a security vulnerability within Laravel, please send an e-mail to Taylor Otwell via [taylor@laravel.com](mailto:taylor@laravel.com). All security vulnerabilities will be promptly addressed.

## License

The Laravel framework is open-sourced software licensed under the [MIT license](https://opensource.org/licenses/MIT).
