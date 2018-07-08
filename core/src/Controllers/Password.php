<?php namespace EvolutionCMS\Controllers;

use EvolutionCMS\Models;
use EvolutionCMS\Interfaces\ManagerTheme;

class Password extends AbstractController implements ManagerTheme\PageControllerInterface
{
    protected $view = 'page.password';

    /**
     * @inheritdoc
     */
    public function checkLocked(): ?string
    {
        $out = Models\ActiveUser::locked(28)->first();
        if ($out !== null) {
            $out = sprintf($this->managerTheme->getLexicon('error_no_privileges'), $out->username);
        }

        return $out;
    }

    /**
     * @inheritdoc
     */
    public function canView(): bool
    {
        return evolutionCMS()->hasPermission('change_password');
    }
}
