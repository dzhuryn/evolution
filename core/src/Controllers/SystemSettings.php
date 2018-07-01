<?php namespace EvolutionCMS\Controllers;

use EvolutionCMS\Interfaces\ManagerController;
use EvolutionCMS\Interfaces\ManagerThemeInterface;
use EvolutionCMS\Models;

class SystemSettings implements ManagerController
{
    /**
     * @var ManagerThemeInterface
     */
    protected $managerTheme;

    public function __construct(ManagerThemeInterface $managerTheme)
    {
        $this->managerTheme = $managerTheme;
    }

    public function canView(): bool
    {
        return evolutionCMS()->hasPermission('settings');
    }

    /**
     * check to see the edit settings page isn't locked
     * @return null|string
     */
    public function checkLocked() : ?string
    {
        $out = Models\ActiveUser::locked(17)
            ->first();
        if ($out !== null) {
            $out = sprintf($this->managerTheme->getLexicon('lock_settings_msg'), $out->username);
        }

        return $out;
    }

    public function render() : string
    {
        /**
         * @var Models\ActiveUser $activeUser
         */

        // end check for lock

        return $this->managerTheme
            ->view('page.systemSettings', [
                'pwd_hash' => $this->getPasswordHash(),
                'gdAvailable' => $this->checkGD(),
                'settings' => $this->getSettings(),
                'displayStyle' => ($_SESSION['browser'] === 'modern') ? 'table-row' : 'block',
                'file_browsers' => $this->getFileBrowsers(),
                'themes' => $this->getThemes(),
                'serverTimes' => $this->getServerTimes(),
                'phxEnabled' => Models\SitePlugin::activePhx()->count(),
                'lang_keys_select' => $this->getLang(),
                'templates' => $this->getTemplates()
            ])->render();
    }

    public function getTemplates()
    {
        $database = evolutionCMS()->getDatabase();
        // load templates
        $rs = $database->select(
            't.templatename, t.id, c.category',
            $database->getFullTableName('site_templates') . " AS t LEFT JOIN " .
            $database->getFullTableName('categories') . " AS c ON t.category = c.id", "",
            'c.category, t.templatename ASC'
        );

        $templates = [];
        $currentCategory = '';
        $oldTmpId = 0;
        $oldTmpName = '';
        $i = 0;
        while ($row = $database->getRow($rs)) {
            $thisCategory = $row['category'];
            if ($thisCategory == null) {
                $thisCategory = $this->managerTheme->getLexicon('no_category');
            }
            if ($thisCategory != $currentCategory) {
                $templates[$i] = [
                    'optgroup' => [
                        'name' => $thisCategory,
                        'options' => []
                    ]
                ];
            } else {
                $i++;
            }
            if ($row['id'] == get_by_key(evolutionCMS()->config, 'default_template')) {
                $oldTmpId = $row['id'];
                $oldTmpName = $row['templatename'];
            }
            $templates[$i]['optgroup']['options'][] = [
                'text' => $row['templatename'],
                'value' => $row['id']
            ];
            $currentCategory = $thisCategory;
        }

        return $templates;
    }

    /**
     * load languages and keys
     *
     * @return array
     */
    public function getLang()
    {
        $lang_keys_select = [];
        $dir = dir(MODX_MANAGER_PATH . 'includes/lang');
        while ($file = $dir->read()) {
            if (strpos($file, '.inc.php') > 0) {
                $endpos = strpos($file, '.');
                $languagename = substr($file, 0, $endpos);
                $lang_keys_select[$languagename] = $languagename;
            }
        }
        $dir->close();

        return $lang_keys_select;
    }
    public function getServerTimes()
    {
        $serverTimes = [];
        for ($i = -24; $i < 25; $i++) {
            $seconds = $i * 60 * 60;
            $serverTimes[] = [
                'value' => $seconds,
                'text' => $i
            ];
        }

        return $serverTimes;
    }

    public function getThemes()
    {
        $themes = [];
        $dir = dir("media/style/");
        while ($file = $dir->read()) {
            if ($file !== "." && $file !== ".." && is_dir("media/style/$file") && substr($file, 0, 1) != '.') {
                if ($file === 'common') {
                    continue;
                }
                $themes[$file] = $file;
            }
        }
        $dir->close();

        return $themes;
    }

    public function getFileBrowsers()
    {
        $out = [];
        foreach (glob("media/browser/*", GLOB_ONLYDIR) as $dir) {
            $dir = str_replace('\\', '/', $dir);
            $out[] = substr($dir, strrpos($dir, '/') + 1);
        }

        return $out;
    }

    public function getSettings()
    {
        // reload system settings from the database.
        // this will prevent user-defined settings from being saved as system setting
        $out = include EVO_CORE_PATH . 'factory/settings.php';
        $out = array_merge(
            \is_array($out) ? $out : [],
            Models\SystemSetting::all()
                ->pluck('setting_value', 'setting_name')
                ->toArray(),
            evolutionCMS()->config
        );

        $out['filemanager_path'] = preg_replace(
            '@^' . preg_quote(MODX_BASE_PATH) . '@',
            '[(base_path)]',
            get_by_key($out, 'filemanager_path')
        );
        $out['rb_base_dir'] = preg_replace(
            '@^' . preg_quote(MODX_BASE_PATH) . '@',
            '[(base_path)]',
            get_by_key($out, 'rb_base_dir')
        );

        if (!$this->checkGD()) {
            $out['use_captcha'] = 0;
        }

        return $out;
    }

    protected function checkGD()
    {
        return extension_loaded('gd');
    }

    public function getPasswordHash() : array
    {
        $managerApi = evolutionCMS()->getManagerApi();
        return [
            'BLOWFISH_Y' => [
                'value' => 'BLOWFISH_Y',
                'text' => 'CRYPT_BLOWFISH_Y (salt &amp; stretch)',
                'disabled' => $managerApi->checkHashAlgorithm('BLOWFISH_Y') ? 0 : 1
            ],
            'BLOWFISH_A' => [
                'value' => 'BLOWFISH_A',
                'text' => 'CRYPT_BLOWFISH_A (salt &amp; stretch)',
                'disabled' => $managerApi->checkHashAlgorithm('BLOWFISH_A') ? 0 : 1
            ],
            'SHA512' => [
                'value' => 'SHA512',
                'text' => 'CRYPT_SHA512 (salt &amp; stretch)',
                'disabled' => $managerApi->checkHashAlgorithm('SHA512') ? 0 : 1
            ],
            'SHA256' => [
                'value' => 'SHA256',
                'text' => 'CRYPT_SHA256 (salt &amp; stretch)',
                'disabled' => $managerApi->checkHashAlgorithm('SHA256') ? 0 : 1
            ],
            'MD5' => [
                'value' => 'MD5',
                'text' => 'CRYPT_MD5 (salt &amp; stretch)',
                'disabled' => $managerApi->checkHashAlgorithm('MD5') ? 0 : 1
            ],
            'UNCRYPT' => [
                'value' => 'UNCRYPT',
                'text' => 'UNCRYPT(32 chars salt + SHA-1 hash)',
                'disabled' => $managerApi->checkHashAlgorithm('UNCRYPT') ? 0 : 1
            ],
        ];
    }
}