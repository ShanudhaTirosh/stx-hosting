<?php

use App\Models\Service;
use App\Models\ServiceApplication;

it('resolves the template port from service_type even when the display name differs', function () {
    expect(data_get(get_service_templates(), 'bookstack.port'))->toBe('80');

    $service = new Service([
        'name' => 'api-smoke-bookstack',
        'service_type' => 'bookstack',
        'docker_compose_raw' => <<<'YAML'
services:
  bookstack:
    image: lscr.io/linuxserver/bookstack:latest
    environment:
      - SERVICE_URL_BOOKSTACK
YAML,
    ]);

    expect($service->getRequiredPort())->toBe(80);

    $app = new ServiceApplication(['name' => 'bookstack']);
    $app->setRelation('service', $service);

    expect($app->getRequiredPort())->toBe(80);
});

it('does not infer a template port from a custom display name', function () {
    $service = new Service(['name' => 'grafana-custom', 'service_type' => null]);

    expect($service->getRequiredPort())->toBeNull();
});
