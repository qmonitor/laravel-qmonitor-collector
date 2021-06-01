<?php

namespace Qmonitor\Tests\Commands;

use Illuminate\Http\Client\Request;
use Illuminate\Support\Facades\Config;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Queue;
use Illuminate\Support\Str;
use Qmonitor\Tests\TestCase;
use sixlive\DotenvEditor\DotenvEditor;

class QmonitorSetupCommandTest extends TestCase
{
    public function setUp(): void
    {
        parent::setUp();

        $this->appUuid = Str::uuid();
        $this->secret = sprintf('qmsec_%s', Str::random(32));

        Config::set([
            'qmonitor.endpoint' => 'https://fail.qmonitor.io',
        ]);

        Http::fake([
            'https://fail.qmonitor.io/apps/setup-key/setup' => Http::response([
                'app_uuid' => $this->appUuid,
            ], 200),
            'https://fail.qmonitor.io/apps/{$this->appUuid}/hearbeat' => Http::response([], 201),
            "*" => Http::response(['message' => 'Ole!'], 200),
        ]);

        Queue::fake();

        touch(base_path('.env.example'));
        touch(base_path('.env'));
    }

    public function tearDown(): void
    {
        unlink(base_path('.env.example'));
        unlink(base_path('.env'));
    }

    /** @test */
    public function it_handles_existing_environment_config()
    {
        $this->loadEnv(base_path('.env'))
            ->set('QMONITOR_APP_ID', 'test id')
            ->set('QMONITOR_SECRET', 'test secret')
            ->save();

        $this->artisan('qmonitor:setup setup-key')
            ->expectsOutput('Your app is aleady configured.')
            ->expectsOutput('If you want to reconfigure your app, remove the QMONITOR_APP_ID and QMONITOR_SECRET keys from your .env file and run this command again.')
            ->assertExitCode(1);

        $this->assertEmpty(Http::recorded(null));

        // reset env state
        // unlink(base_path('.env'));
        // touch(base_path('.env'));
    }

    /** @test */
    public function it_sets_up_the_required_config_keys()
    {
        if (file_exists(config_path('qmonitor.php'))) {
            unlink(config_path('qmonitor.php'));
        }

        Config::set([
            'qmonitor.app_id' => null,
            'qmonitor.signing_secret' => null,
        ]);

        $this->assertFalse(file_exists(config_path('qmonitor.php')));

        $this->artisan('qmonitor:setup', [
            'setup_key' => 'setup-key',
            '--secret' => $this->secret,
        ])
            ->expectsOutput('Your config file was updated, but feel free to add these configs to your other environments:')
            ->assertExitCode(0);

        $this->assertTrue(file_exists(config_path('qmonitor.php')));

        tap($this->loadEnv(base_path('.env')), function ($file) {
            $this->assertEquals($this->appUuid, $file->getEnv('QMONITOR_APP_ID'));
            $this->assertEquals($this->secret, $file->getEnv('QMONITOR_SECRET'));
        });

        $this->assertEquals($this->appUuid, Config::get('qmonitor.app_id'));
        $this->assertEquals($this->secret, Config::get('qmonitor.signing_secret'));

        Http::assertSent(function (Request $request) {
            if ($request->url() === 'https://fail.qmonitor.io/apps/setup-key/setup') {
                return $request->hasHeader('Signature') && ! empty($request['signing_secret']);
            }

            return false;
        });

        Http::assertSent(function (Request $request) {
            if ($request->url() === "https://fail.qmonitor.io/apps/{$this->appUuid}/heartbeat") {
                return $request['hostname'] == gethostname() && $request['environment'] == 'testing';
            }

            return false;
        });
    }

    protected function loadEnv($file)
    {
        return tap(new DotenvEditor)->load($file);
    }
}
