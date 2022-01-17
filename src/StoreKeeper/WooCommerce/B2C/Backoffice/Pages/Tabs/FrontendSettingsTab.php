<?php

namespace StoreKeeper\WooCommerce\B2C\Backoffice\Pages\Tabs;

use StoreKeeper\WooCommerce\B2C\Backoffice\Pages\AbstractTab;
use StoreKeeper\WooCommerce\B2C\Backoffice\Pages\FormElementTrait;
use StoreKeeper\WooCommerce\B2C\I18N;
use StoreKeeper\WooCommerce\B2C\Options\StoreKeeperOptions;

class FrontendSettingsTab extends AbstractTab
{
    use FormElementTrait;
    public const SAVE_ACTION = 'save-action';

    public function __construct(string $title, string $slug = '')
    {
        parent::__construct($title, $slug);

        $this->addAction(self::SAVE_ACTION, [$this, 'saveAction']);
    }

    protected function getStylePaths(): array
    {
        return [];
    }

    public function render(): void
    {
        $url = $this->getActionUrl(self::SAVE_ACTION);
        $this->renderFormStart('post', $url);

        $this->renderTheme();
        $this->renderPostcodeChecking();

        $this->renderFormActionGroup(
            $this->getFormButton(
                __('Save settings', I18N::DOMAIN),
                'button-primary'
            )
        );

        $this->renderFormEnd();
    }

    private function renderTheme(): void
    {
        $this->renderFormGroup(
            esc_html__('Currently installed theme', I18N::DOMAIN),
            esc_html(wp_get_theme())
        );
    }

    private function renderPostcodeChecking(): void
    {
        $validateCustomerAddressName = StoreKeeperOptions::getConstant(StoreKeeperOptions::VALIDATE_CUSTOMER_ADDRESS);
        $this->renderFormGroup(
            __('Validate NL customer address', I18N::DOMAIN),
            $this->getFormCheckbox(
                $validateCustomerAddressName,
                'yes' === StoreKeeperOptions::get($validateCustomerAddressName, 'yes'),
            ).' '.__(
                'When checked, billing and shipping addresses will be validated on customer\'s account edit and checkout page when selected country is Netherlands',
                I18N::DOMAIN
            )
        );
    }

    public function saveAction()
    {
        $validateAddress = StoreKeeperOptions::getConstant(StoreKeeperOptions::VALIDATE_CUSTOMER_ADDRESS);

        $data = [
            $validateAddress => 'on' === sanitize_key($_POST[$validateAddress]) ? 'yes' : 'no',
        ];

        foreach ($data as $key => $value) {
            update_option($key, $value);
        }

        wp_redirect(remove_query_arg('action'));
    }
}
