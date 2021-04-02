<?php

namespace Qmonitor\Commands;

use Exception;
use Qmonitor\Qmonitor;
use Illuminate\Support\Str;
use InvalidArgumentException;
use Illuminate\Console\Command;
use Qmonitor\QmonitorServiceProvider;
use Illuminate\Support\Facades\Config;
use sixlive\DotenvEditor\DotenvEditor;
use Qmonitor\Jobs\QmonitorHeartbeatJob;
use Illuminate\Http\Client\RequestException;

class QmonitorSetup extends Command
{
    public $signature = 'qmonitor:setup {app_id : The UUID of the qmonitor.io application you are setting up}';

    public $description = 'Setup Qmonitor configs and send test heartbeat to qmonitor.io';

    /**
     * @var string
     */
    protected $signingSecret;

    public function handle()
    {
        // prevent accidental setup runs
        if (! $this->checkExistingSetup()) {
            return static::FAILURE;
        }

        if (! $this->generateSigningSecret()) {
            return $this->failWithError('The signing secret was not generated.');
        }

        if (! $this->publishConfig()) {
            return $this->failWithError('The config file was not published.');
        }

        if (! $this->writeEnvFile()) {
            return $this->failWithError('The .env file was not updated.');
        }

        if (! $this->writeEnvExampleFile()) {
            $this->error('The .env.example file was not updated.');
        }

        if (! $this->sendSetupPayload()) {
            return $this->failWithError('The setup payload was not sent.');
        }

        $this->sendTestHeartBeat();

        $this->newLine();
        $this->info('Hooray! Queue monitoring is up and running!');

        $this->newLine();
        $this->warn('Add these configs to all your environments:');
        $this->comment(str_repeat('*', 54));
        $this->line(sprintf('QMONITOR_APP_ID=%s', Config::get('qmonitor.app_id')));
        $this->line(sprintf('QMONITOR_SECRET=%s', Config::get('qmonitor.signing_secret')));
        $this->comment(str_repeat('*', 54));
        $this->newLine();
    }

    protected function checkExistingSetup()
    {
        $app = Config::get('qmonitor.app_id');
        $secret = Config::get('qmonitor.signing_secret');

        if (empty($app) && empty($secret)) {
            $this->task('Checking for existing app configs');
            return true;
        }

        $this->newLine();
        $this->error('Your app is aleady configured.');
        $this->warn('If you want to reconfigure your app, remove the QMONITOR_APP_ID and QMONITOR_SECRET keys from your .env file and run this command again.');
        $this->newLine();

        return false;
    }

    protected function generateSigningSecret()
    {
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
            $this->newLine();

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
                    ->set('QMONITOR_APP_ID', $this->argument('app_id'))
                    ->set('QMONITOR_SECRET', $this->signingSecret)
                    ->save();
            } catch (InvalidArgumentException $e) {
                return false;
            }

            Config::set([
                'qmonitor.app_id' => $this->argument('app_id'),
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
                Qmonitor::sendSetup([
                    'signing_secret' => $this->signingSecret,
                ]);

                return true;
            } catch (RequestException $e) {
                $this->newLine();
                $this->error($e->response['message']);

                return false;
            } catch (Exception $e) {
                $this->newLine();
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
                $this->newLine();
                $this->error($e->response['message']);

                return false;
            } catch (Exception $e) {
                $this->newLine();
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
