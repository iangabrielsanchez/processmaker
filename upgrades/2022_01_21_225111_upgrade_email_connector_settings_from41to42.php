<?php

use ProcessMaker\Models\Setting;
use ProcessMaker\Upgrades\UpgradeMigration;
use ProcessMaker\Packages\Connectors\Email\EmailConfig;
use Illuminate\Support\Facades\Artisan;
use Illuminate\Support\Facades\File;
use Illuminate\Support\Str;

class UpgradeEmailConnectorSettingsFrom41to42 extends UpgradeMigration
{
    /**
     * The version of ProcessMaker being upgraded *to*
     *
     * @var string example: 4.2.28
     */
    public $from = '4.1.*';

    /**
     * The version of ProcessMaker being upgraded *from*
     *
     * @var string example: 4.1.23
     */
    public $to = '4.2.29-RC';

    /**
     * Upgrade migration cannot be skipped if the pre-upgrade checks fail
     *
     * @var bool
     */
    public $required = true;

    /**
     * @var string
     */
    protected $driver;

    /**
     * @var \ProcessMaker\Models\Setting|null
     */
    protected $setting;

    /**
     * @var array
     */
    protected $drivers;

    /**
     * @var array
     */
    protected $encryption;

    /**
     * @var string
     */
    protected $prefix;

    /**
     * @var array
     */
    protected $settings_groups;

    /**
     * @param  \ProcessMaker\Packages\Connectors\Email\EmailConfig  $config
     */
    public function __construct()
    {
        $config = new EmailConfig();

        $this->drivers = $config::drivers;
        $this->encryption = $config::encryption;
        $this->prefix = $config::prefix;
        $this->settings_groups = $config::settings_groups;
    }

    /**
     * Run any validations/pre-run checks to ensure the environment, settings,
     * packages installed, etc. are right correct to run this upgrade.
     *
     * There is no need to check against the version(s) as the upgrade
     * migrator will do this automatically and fail if the correct
     * version(s) are not present.
     *
     * Throw a RuntimeException if the conditions to run this upgrade migration
     * are *NOT* correct. If this is not a required upgrade, then it will be
     * skipped. Otherwise, the thrown exception will stop the remaining
     * upgrade migrations from running.
     *
     * @return void
     * @throws \Exception
     */
    public function preflightChecks()
    {
        $this->clearCache();

        if (!$this->connectorSendEmailInstalled()) {
            throw new RuntimeException('The package connector-send-email must be installed to run this upgrade.');
        }

        // If the .env file doesn't exist, we have nothing to upgrade from
        if (!$this->envFileExists()) {
            throw new RuntimeException('The .env file is missing');
        }

        // If we don't know which driver we're taking from the
        // .env file, then we shouldn't continue since we won't
        // know where to set all of the other settings, e.g.
        // MAIL_HOST, MAIL_ENCRYPTION, etc.
        if (!$this->emailDriver()) {
            throw new RuntimeException('The MAIL_DRIVER environment variable was not found in the .env file. This is required to run the upgrade process.');
        }

        // Validate the driver and bail if it's not supported
        if (!in_array($this->emailDriver(), $this->drivers, true)) {
            throw new RuntimeException("Unsupported MAIL_DRIVER found in the .env file: \"".$this->emailDriver()."\"");
        }

        // First, let's find the primary mail driver setting
        $this->emailSetting(
            $mail_driver_key = $this->prefix.'MAIL_DRIVER'
        );

        // Double-check to make sure we found the right Setting and if so,
        // all of our pre-flight checks before running the upgrade
        if (!$this->emailSetting() instanceof Setting) {
            throw new RuntimeException("Setting with key \"$mail_driver_key\" was not found.");
        }
    }

    /**
     * Run the migrations.
     *
     * @return void
     * @throws \Exception
     */
    public function up()
    {
        // Now update the mail driver setting
        $updated = $this->emailSetting()->fill([
            'config' => (string) array_search($this->emailDriver(), $this->drivers, true)
        ])->save();

        // Update the setting and remove it from .env
        if ($updated) {
            $this->removeEnvValue('MAIL_DRIVER');
        }

        // Loop through the variables for a given driver
        // and if we find the value, create a setting with
        // the variable name/value and then remove it
        // from the .env file
        foreach ($this->settings_groups[$this->emailDriver()] as $variable_name) {
            // We've already set this so we can skip it
            if ($variable_name === 'MAIL_DRIVER') {
                continue;
            }

            $env_value = env($variable_name);

            if (null === $env_value) {
                continue;
            }

            if ($variable_name === 'MAIL_ENCRYPTION') {
                $env_value = (string) array_search($env_value, array_values($this->encryption), true);
            }

            $setting = Setting::byKey(
                $setting_key = $this->prefix.$variable_name
            );

            if (!$setting instanceof Setting) {
                logger()->warning("Warning while running upgrade migration: Setting with key: $setting_key not found, skipping...", [
                    'migration' => __FILE__
                ]);

                continue;
            }

            $setting->fill([
                'config' => $env_value
            ]);

            if ($setting->save()) {
                $this->removeEnvValue($variable_name);
            }

            $this->clearCache();
        }
    }

    /**
     * Reverse the migrations.
     *
     * @return void
     */
    public function down()
    {
        //
    }

    /**
     * @return void
     */
    public function clearCache()
    {
        Artisan::call('optimize:clear', [
            '--quiet' => true
        ]);
    }

    /**
     * @return mixed|string
     */
    public function emailDriver()
    {
        if (blank($this->driver)) {
            $this->driver = env('MAIL_DRIVER');
        }

        return $this->driver;
    }

    /**
     * @param  string  $key
     *
     * @return \ProcessMaker\Models\Setting|null
     * @throws \Exception
     */
    public function emailSetting(string $key = null)
    {
        if (!$this->setting instanceof Setting && is_string($key)) {
            $this->setting = Setting::byKey($key);
        }

        return $this->setting;
    }

    /**
     * @return bool
     */
    protected function connectorSendEmailInstalled()
    {
        return File::exists(base_path('vendor/processmaker/connector-send-email'));
    }

    /**
     * @return bool
     */
    protected function envFileExists()
    {
        return File::exists(base_path('.env'));
    }

    /**
     * Remove any line(s) containing a set variable in .env
     *
     * @param  string  $variable_name
     *
     * @return void
     */
    protected function removeEnvValue(string $variable_name)
    {
        if (!$this->envFileExists()) {
            return;
        }

        $file_contents = '';
        $file_path = base_path('.env');
        $file_handle = fopen($file_path, 'rb');

        if (!is_resource($file_handle)) {
            return;
        }

        while (($line = fgets($file_handle)) !== false) {
            if (!Str::contains($line, $variable_name)) {
                $file_contents .= $line;
            }
        }

        fclose($file_handle);
        file_put_contents($file_path, $file_contents);
    }
}