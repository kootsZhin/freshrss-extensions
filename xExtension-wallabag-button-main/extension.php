<?php

class WallabagButtonExtension extends Minz_Extension
{
  #[\Override]
  public function init(): void
  {
    $this->registerTranslates();

    Minz_View::appendScript($this->getFileUrl('script.js'), false, false, false);
    Minz_View::appendStyle($this->getFileUrl('style.css'));
    Minz_View::appendScript(strval(_url('wallabagButton', 'jsVars')), false, true, false);

    $this->registerController('wallabagButton');
    $this->registerViews();
  }

  #[\Override]
  public function handleConfigureAction(): void
  {
    $this->registerTranslates();
    if (!Minz_Request::isPost()) {
      return;
    }

    $keyboard_shortcut = Minz_Request::paramString('wallabag_keyboard_shortcut');
    FreshRSS_Context::userConf()->_attribute('wallabag_keyboard_shortcut', $keyboard_shortcut);

    $cainfo_path = Minz_Request::paramString('wallabag_cainfo_path');
    FreshRSS_Context::userConf()->_attribute('wallabag_cainfo_path', $cainfo_path);

    FreshRSS_Context::userConf()->save();

    $button_location = Minz_Request::paramString('wallabag_button_location');
    $url_redirect = array('c' => 'extension', 'a' => 'configure', 'params' => array('e' => 'Wallabag Button'));

    switch ($button_location) {
      case "header_bottom":
      case "header":
      case "bottom":
      case "hidden":
        FreshRSS_Context::userConf()->_attribute('wallabag_button_location', $button_location);
        FreshRSS_Context::userConf()->save();

        Minz_Request::good(_t('ext.wallabagButton.notifications.changes_saved_sucessfully'), $url_redirect);
        return;
      default:
        Minz_Request::bad(_t('ext.wallabagButton.notifications.changes_failed', $button_location), $url_redirect);
    }
  }

  public function isConfigured(): bool
  {
    return FreshRSS_Context::userConf()->attributeString('wallabag_access_token') != '';
  }

  public function shouldBeShown(string $entryName): bool
  {
    $location = FreshRSS_Context::userConf()->attributeString('wallabag_button_location');

    // TO BE REMOVED:
    // Update missing entry after update
    if ($location == "") {
      FreshRSS_Context::userConf()->_attribute('wallabag_button_location', "header_bottom");
      FreshRSS_Context::userConf()->save();
      return true;
    }

    if ($location == "hidden") {
      return false;
    } else if ($location == "header_bottom") {
      return true;
    }
    return $entryName == $location;
  }
}
