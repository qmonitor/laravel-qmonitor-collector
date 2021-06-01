<?php

namespace Qmonitor\Commands;

use Exception;
use Illuminate\Console\Command;
use Illuminate\Http\Client\RequestException;
use Illuminate\Support\Facades\Config;
use Illuminate\Support\Str;
use InvalidArgumentException;
use Qmonitor\Qmonitor;
use Qmonitor\QmonitorServiceProvider;
use sixlive\DotenvEditor\DotenvEditor;

class QmonitorSetupCommand extends Command
{
    /**
     * @var string
     */
    public $signature = 'qmonitor:setup
        {setup_key : The app setup key of the qmonitor.io application you are setting up}
        {--secret= : The signing secret to use}';

    /**
     * @var string
     */
    public $description = 'Setup Qmonitor configs and send test heartbeat to qmonitor.io';

    /**
     * @var string
     */
    protected $signingSecret;

    /**
     * @var string
     */
    protected $appId;

    public function handle()
    {
        // prevent accidental setup runs
        if (! $this->checkExistingSetup()) {
            return 1;
        }

        if (! $this->generateSigningSecret()) {
            return $this->failWithError('The signing secret was not generated.');
        }

        if (! $this->publishConfig()) {
            return $this->failWithError('The config file was not published.');
        }

        if (! $this->sendSetupPayload()) {
            return $this->failWithError('The setup payload was not sent.');
        }

        if (! $this->writeEnvFile()) {
            return $this->failWithError('The .env file was not updated.');
        }

        if (! $this->writeEnvExampleFile()) {
            $this->error('The .env.example file was not updated.');
        }

        $this->sendTestHeartBeat();

        $this->line('');
        $this->info('Hooray! Queue monitoring is up and running!');

        $this->line('');
        $this->warn('Your config file was updated, but feel free to add these configs to your other environments:');
        $this->comment(str_repeat('*', 54));
        $this->line(sprintf('QMONITOR_APP_ID=%s', Config::get('qmonitor.app_id')));
        $this->line(sprintf('QMONITOR_SECRET=%s', Config::get('qmonitor.signing_secret')));
        $this->comment(str_repeat('*', 54));
        $this->line('');
    }

    protected function checkExistingSetup()
    {
        $app = Config::get('qmonitor.app_id');
        $secret = Config::get('qmonitor.signing_secret');

        if (empty($app) && empty($secret)) {
            $this->task('Checking for existing app configs');

            return true;
        }

        $this->line('');
        $this->error('Your app is aleady configured.');
        $this->warn('If you want to reconfigure your app, remove the QMONITOR_APP_ID and QMONITOR_SECRET keys from your .env file and run this command again.');
        $this->line('');

        return false;
    }

    protected function generateSigningSecret()
    {
        if ($secret = $this->option('secret')) {
            $this->signingSecret = $secret;

            return true;
        }

        return $this->task('Generating signing secret', function () {
            $this->signingSecret = sprintf('qmsec_%s', Str::random(32));

            return true;
        }, 'generating...');
    }

    protected function publishConfig()
    {
        if (file_exists(config_path('qmonitor.php'))) {
            return true;
        }

        return $this->task('Publishing config file', function () {
            $this->line('');

            return $this->call('vendor:publish', [
                '--provider' => QmonitorServiceProvider::class,
            ]) === 0;
        }, 'publishing...');
    }

    protected function writeEnvFile()
    {
        return $this->task('Adding config keys to .env file', function () {
            try {
                (new DotenvEditor)
                    ->load(base_path('.env'))
                    ->heading('qmonitor.io')
                    ->set('QMONITOR_APP_ID', $this->appId)
                    ->set('QMONITOR_SECRET', $this->signingSecret)
                    ->save();
            } catch (InvalidArgumentException $e) {
                return false;
            }

            Config::set([
                'qmonitor.app_id' => $this->appId,
                'qmonitor.signing_secret' => $this->signingSecret,
            ]);

            if (file_exists(app()->getCachedConfigPath())) {
                $this->call('config:cache');
            }

            return true;
        }, 'adding...');
    }

    protected function writeEnvExampleFile()
    {
        return $this->task('Adding config keys to .env.example file', function () {
            try {
                (new DotenvEditor)
                    ->load(base_path('.env.example'))
                    ->heading('qmonitor.io')
                    ->set('QMONITOR_APP_ID', '')
                    ->set('QMONITOR_SECRET', '')
                    ->save();

                return true;
            } catch (InvalidArgumentException $e) {
                return false;
            }
        }, 'adding...');
    }

    protected function sendSetupPayload()
    {
        return $this->task('Sending setup payload to qmonitor.io', function () {
            try {
                $payload = Qmonitor::sendSetup($this->argument('setup_key'), [
                    'signing_secret' => $this->signingSecret,
                ]);

                ray($payload);

                $this->appId = $payload['app_uuid'];

                return true;
            } catch (RequestException $e) {
                $this->line('');
                $this->error($e->response['message']);

                return false;
            } catch (Exception $e) {
                $this->line('');
                $this->error($e->getMessage());

                return false;
            }
        }, 'sending...');
    }

    protected function sendTestHeartBeat()
    {
        return $this->task('Sending test hearbeat to qmonitor.io', function () {
            try {
                Qmonitor::sendHeartbeat();

                return true;
            } catch (RequestException $e) {
                $this->line('');
                $this->error($e->response['message']);

                return false;
            } catch (Exception $e) {
                $this->line('');
                $this->error($e->getMessage());

                return false;
            }
        }, 'sending...');
    }

    protected function failWithError($message)
    {
        $this->error($message);

        return static::FAILURE;
    }
}
