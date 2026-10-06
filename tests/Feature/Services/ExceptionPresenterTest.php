<?php

namespace Tests\Feature\Services;

use App\Services\ExceptionPresenter;
use Illuminate\Auth\Access\AuthorizationException;
use Illuminate\Database\Eloquent\ModelNotFoundException;
use Illuminate\Database\QueryException;
use PDOException;
use RuntimeException;
use Tests\TestCase;

class ExceptionPresenterTest extends TestCase
{
    private string $locale;

    protected function setUp(): void
    {
        parent::setUp();
        $this->locale = app()->getLocale();
        app()->setLocale('en');
    }

    protected function tearDown(): void
    {
        app()->setLocale($this->locale);
        parent::tearDown();
    }

    private function queryException(int $driverCode): QueryException
    {
        $previous = new PDOException('driver error');
        $previous->errorInfo = ['HY000', $driverCode, 'driver error'];

        return new QueryException('mysql', 'select 1', [], $previous);
    }

    public function test_reference_code_follows_the_reference_format_and_is_embedded_in_the_body(): void
    {
        $presented = ExceptionPresenter::present(new RuntimeException('boom'));

        $this->assertMatchesRegularExpression('/^ERR-\d{6}-\d{6}-[A-Z0-9]{4}$/', $presented['reference']);
        $this->assertStringContainsString($presented['reference'], $presented['body']);
        $this->assertStringNotContainsString('errors/strings.notifications', $presented['title'].$presented['body']);
    }

    public function test_query_exception_classifies_a_duplicate_entry(): void
    {
        $presented = ExceptionPresenter::present($this->queryException(1062));

        $this->assertSame('Already exists', $presented['title']);
    }

    public function test_query_exception_classifies_a_null_not_allowed_column(): void
    {
        $presented = ExceptionPresenter::present($this->queryException(1048));

        $this->assertSame('Missing information', $presented['title']);
    }

    public function test_query_exception_classifies_a_deadlock_as_busy(): void
    {
        $presented = ExceptionPresenter::present($this->queryException(1213));

        $this->assertSame('System busy', $presented['title']);
    }

    public function test_query_exception_classifies_a_lost_connection(): void
    {
        $presented = ExceptionPresenter::present($this->queryException(2002));

        $this->assertSame('Connection interrupted', $presented['title']);
    }

    public function test_unknown_driver_code_falls_back_to_the_generic_database_message(): void
    {
        $presented = ExceptionPresenter::present($this->queryException(9999));

        $this->assertSame('Could not save', $presented['title']);
    }

    public function test_model_not_found_gets_its_dedicated_message(): void
    {
        $presented = ExceptionPresenter::present(new ModelNotFoundException);

        $this->assertSame('Record not found', $presented['title']);
    }

    public function test_authorization_exception_gets_its_dedicated_message(): void
    {
        $presented = ExceptionPresenter::present(new AuthorizationException);

        $this->assertSame('Not permitted', $presented['title']);
    }

    public function test_unclassified_exception_gets_the_generic_message(): void
    {
        $presented = ExceptionPresenter::present(new RuntimeException('boom'));

        $this->assertSame('Something went wrong', $presented['title']);
    }
}