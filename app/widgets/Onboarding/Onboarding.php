<?php

class Onboarding extends \Movim\Widget\Base
{
    function load()
    {
        $this->addcss('onboarding.css');
        $this->addjs('onboarding.js');
    }

    public function ajaxAskNotifications()
    {
        $tpl = $this->tpl();
        $this->rpc('Onboarding.setNotifications');
        Dialog::fill($tpl->draw('_onboarding_notifications', true));
    }

    public function ajaxAskPublic()
    {
        $tpl = $this->tpl();
        $this->rpc('Onboarding.setPublic');

        if (App\User::me()->public == null) {
            Dialog::fill($tpl->draw('_onboarding_public', true));
        }
    }

    public function ajaxAskPopups()
    {
        $tpl = $this->tpl();
        Dialog::fill($tpl->draw('_onboarding_popups', true));
        $this->rpc('Onboarding.setPopups');
    }

    public function ajaxEnablePublic()
    {
        App\User::me()->setPublic();
        Notification::append(null, $this->__('vcard.public'));
    }
}
