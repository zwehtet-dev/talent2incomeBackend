<?php

declare(strict_types=1);

describe('Architecture Rules', function () {
    it('ensures models extend Eloquent', function () {
        expect('App\Models')
            ->toExtend('Illuminate\Database\Eloquent\Model');
    });

    it('ensures controllers extend base controller', function () {
        expect('App\Http\Controllers')
            ->toExtend('App\Http\Controllers\Controller');
    });

    it('ensures requests extend form request', function () {
        expect('App\Http\Requests')
            ->toExtend('Illuminate\Foundation\Http\FormRequest');
    });

    it('ensures policies extend base policy', function () {
        expect('App\Policies')
            ->toExtend('Illuminate\Auth\Access\HandlesAuthorization');
    });

    it('ensures jobs implement should queue', function () {
        expect('App\Jobs')
            ->toImplement('Illuminate\Contracts\Queue\ShouldQueue');
    });

    it('ensures middleware implements middleware contract', function () {
        expect('App\Http\Middleware')
            ->toImplement('Illuminate\Contracts\Http\Middleware');
    });
});

describe('Code Quality Rules', function () {
    it('ensures no debugging functions are used', function () {
        expect(['dd', 'dump', 'var_dump', 'print_r', 'die', 'exit'])
            ->not->toBeUsed();
    });

    it('ensures strict types are declared', function () {
        expect('App')
            ->toUseStrictTypes();
    });

    it('ensures no global functions are used in models', function () {
        expect('App\Models')
            ->not->toUse(['session', 'request', 'response', 'redirect']);
    });
});

describe('Security Rules', function () {
    it('ensures no eval functions are used', function () {
        expect(['eval', 'exec', 'shell_exec', 'system', 'passthru'])
            ->not->toBeUsed();
    });

    it('ensures sensitive data is not logged', function () {
        expect('App')
            ->not->toUse(['Log::info', 'Log::debug'])
            ->ignoring('App\Http\Middleware\LogRequests');
    });
});

describe('Naming Conventions', function () {
    it('ensures controllers have proper suffix', function () {
        expect('App\Http\Controllers')
            ->toHaveSuffix('Controller');
    });

    it('ensures requests have proper suffix', function () {
        expect('App\Http\Requests')
            ->toHaveSuffix('Request');
    });

    it('ensures policies have proper suffix', function () {
        expect('App\Policies')
            ->toHaveSuffix('Policy');
    });

    it('ensures jobs have proper suffix', function () {
        expect('App\Jobs')
            ->toHaveSuffix('Job');
    });
});
