<?php

use App\Models\Server;
use App\Models\User;
use App\Policies\ServerPolicy;

it('allows any user to view any servers', function () {
    $user = Mockery::mock(User::class)->makePartial();

    $policy = new ServerPolicy;
    expect($policy->viewAny($user))->toBeTrue();
});

it('allows team member to view their own team server', function () {
    $teams = collect([
        (object) ['id' => 1],
    ]);

    $user = Mockery::mock(User::class)->makePartial();
    $user->shouldReceive('getAttribute')->with('teams')->andReturn($teams);

    $server = Mockery::mock(Server::class)->makePartial();
    $server->team_id = 1;

    $policy = new ServerPolicy;
    expect($policy->view($user, $server))->toBeTrue();
});

it('denies non-member to view another team server', function () {
    $teams = collect([
        (object) ['id' => 1],
    ]);

    $user = Mockery::mock(User::class)->makePartial();
    $user->shouldReceive('getAttribute')->with('teams')->andReturn($teams);

    $server = Mockery::mock(Server::class)->makePartial();
    $server->team_id = 2;

    $policy = new ServerPolicy;
    expect($policy->view($user, $server))->toBeFalse();
});

it('allows platform staff to create a server', function () {
    $user = Mockery::mock(User::class)->makePartial();
    $user->shouldReceive('isInstanceAdmin')->andReturn(true);

    $policy = new ServerPolicy;
    expect($policy->create($user))->toBeTrue();
});

it('denies non-staff (including a client who owns their own team) to create a server', function () {
    $user = Mockery::mock(User::class)->makePartial();
    $user->shouldReceive('isInstanceAdmin')->andReturn(false);
    $user->shouldReceive('isAdmin')->andReturn(true); // team owner: must NOT be enough

    $policy = new ServerPolicy;
    expect($policy->create($user))->toBeFalse();
});

it('allows team admin to update their own team server', function () {
    $user = Mockery::mock(User::class)->makePartial();
    $user->shouldReceive('isAdminOfTeam')->with(1)->andReturn(true);

    $server = Mockery::mock(Server::class)->makePartial();
    $server->team_id = 1;

    $policy = new ServerPolicy;
    expect($policy->update($user, $server))->toBeTrue();
});

it('denies team member to update their own team server', function () {
    $user = Mockery::mock(User::class)->makePartial();
    $user->shouldReceive('isAdminOfTeam')->with(1)->andReturn(false);

    $server = Mockery::mock(Server::class)->makePartial();
    $server->team_id = 1;

    $policy = new ServerPolicy;
    expect($policy->update($user, $server))->toBeFalse();
});

// Server delete is owner-only; covered with real roles in Feature/Hosting/Phase0SecurityTest.

it('denies restore for any user', function () {
    $user = Mockery::mock(User::class)->makePartial();

    $server = Mockery::mock(Server::class)->makePartial();
    $server->team_id = 1;

    $policy = new ServerPolicy;
    expect($policy->restore($user, $server))->toBeFalse();
});

it('denies force delete for any user', function () {
    $user = Mockery::mock(User::class)->makePartial();

    $server = Mockery::mock(Server::class)->makePartial();
    $server->team_id = 1;

    $policy = new ServerPolicy;
    expect($policy->forceDelete($user, $server))->toBeFalse();
});

it('allows team admin to manage proxy', function () {
    $user = Mockery::mock(User::class)->makePartial();
    $user->shouldReceive('isAdminOfTeam')->with(1)->andReturn(true);

    $server = Mockery::mock(Server::class)->makePartial();
    $server->team_id = 1;

    $policy = new ServerPolicy;
    expect($policy->manageProxy($user, $server))->toBeTrue();
});

it('denies team member to manage proxy', function () {
    $user = Mockery::mock(User::class)->makePartial();
    $user->shouldReceive('isAdminOfTeam')->with(1)->andReturn(false);

    $server = Mockery::mock(Server::class)->makePartial();
    $server->team_id = 1;

    $policy = new ServerPolicy;
    expect($policy->manageProxy($user, $server))->toBeFalse();
});

it('allows team admin to manage sentinel, ca certificate, and view security', function () {
    $user = Mockery::mock(User::class)->makePartial();
    $user->shouldReceive('isAdminOfTeam')->with(1)->andReturn(true);

    $server = Mockery::mock(Server::class)->makePartial();
    $server->team_id = 1;

    $policy = new ServerPolicy;
    expect($policy->manageSentinel($user, $server))->toBeTrue();
    expect($policy->viewSentinel($user, $server))->toBeTrue();
    expect($policy->manageCaCertificate($user, $server))->toBeTrue();
    expect($policy->viewSecurity($user, $server))->toBeTrue();
});

it('denies team member to view sentinel', function () {
    $user = Mockery::mock(User::class)->makePartial();
    $user->shouldReceive('isAdminOfTeam')->with(1)->andReturn(false);

    $server = Mockery::mock(Server::class)->makePartial();
    $server->team_id = 1;

    $policy = new ServerPolicy;
    expect($policy->viewSentinel($user, $server))->toBeFalse();
});
