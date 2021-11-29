<?php

namespace StoreKeeper\WooCommerce\B2C\Backoffice\Pages\Tabs;

use StoreKeeper\WooCommerce\B2C\Backoffice\Pages\AbstractTab;
use StoreKeeper\WooCommerce\B2C\Backoffice\Pages\FormElementTrait;
use StoreKeeper\WooCommerce\B2C\I18N;
use StoreKeeper\WooCommerce\B2C\Models\TaskModel;
use StoreKeeper\WooCommerce\B2C\Models\WebhookLogModel;
use StoreKeeper\WooCommerce\B2C\Options\StoreKeeperOptions;
use StoreKeeper\WooCommerce\B2C\Options\WooCommerceOptions;
use StoreKeeper\WooCommerce\B2C\Tools\TaskHandler;
use StoreKeeper\WooCommerce\B2C\Tools\TaskRateCalculator;

class ConnectionTab extends AbstractTab
{
    use FormElementTrait;

    const DISCONNECT_ACTION = 'disconnect-action';
    const SAVE_ACTION = 'save-action';

    public function __construct(string $title, string $slug = '')
    {
        parent::__construct($title, $slug);

        $this->addAction(self::DISCONNECT_ACTION, [$this, 'disconnectAction']);
        $this->addAction(self::SAVE_ACTION, [$this, 'saveAction']);
    }

    protected function getStylePaths(): array
    {
        return [];
    }

    public function render(): void
    {
        if (StoreKeeperOptions::isConnected()) {
            $this->renderConnected();
        } else {
            $this->renderDisconnected();
        }

        $this->renderStatistics();

        $this->renderSettings();
    }

    private function renderConnected()
    {
        $this->renderFormStart();

        $url = $this->getActionUrl(self::DISCONNECT_ACTION);
        $api = StoreKeeperOptions::get(StoreKeeperOptions::API_URL);
        $this->renderFormGroup(
            __('Currently connected to', I18N::DOMAIN),
            esc_html($api).' '.$this->getFormLink(
                esc_url($url),
                __('Disconnect', I18N::DOMAIN),
                'button-primary'
            )
        );

        $this->renderFormEnd();
    }

    private function renderDisconnected()
    {
        $this->renderFormStart();

        $key = esc_attr(WooCommerceOptions::getApiKey());
        $this->renderFormGroup(
            __('Backoffice API key', I18N::DOMAIN),
            "<input type='text' readonly onfocus='this.select()' onclick='this.select()' value='$key' />"
        );

        $title = __('Steps', I18N::DOMAIN);
        $this->renderFormGroup('', "<b>$title</b>");

        $steps = [
            '1. '.__('Copy the "Backoffice API Key" from the text area.', I18n::DOMAIN),
            '2. '.__('Log into your admin environment', I18n::DOMAIN),
            '3. '.__('Navigate to settings > technical settings and click on the "webhook" tab.', I18n::DOMAIN),
            '4. '.__('Paste your api key into the field that says "Api key" and click connect.', I18n::DOMAIN),
            '5. '.__('Once done, you should reload this page and you will be fully connected.', I18n::DOMAIN),
        ];
        $this->renderFormGroup('', implode('<br/>', $steps));

        $this->renderFormEnd();
    }

    private function renderStatistics()
    {
        $now = current_time('mysql', 1);
        $calculator = new TaskRateCalculator($now);
        $incomingRate = $calculator->countIncoming();
        $processedRate = $calculator->calculateProcessed();
        $this->renderFormStart();

        $this->renderFormHeader(__('Sync statistics', I18N::DOMAIN));

        $this->renderFormGroup(
            __('Tasks in queue', I18N::DOMAIN),
            TaskModel::count(['status = :status'], ['status' => TaskHandler::STATUS_NEW]).
            sprintf(
                __(
                    ' (new: %s p/h, processing rate: %s p/h)',
                    I18N::DOMAIN
                ),
                $incomingRate,
                $processedRate
            )
        );

        $this->renderFormGroup(
            __('Webhooks log count', I18N::DOMAIN),
            esc_html(WebhookLogModel::count())
        );

        $this->renderFormGroup(
            __('Last task processed', I18N::DOMAIN),
            esc_html($this->getLastTaskProcessed())
        );

        $this->renderFormGroup(
            __('Last webhook received', I18N::DOMAIN),
            esc_html($this->getLastWebhookReceived())
        );

        $this->renderFormEnd();
    }

    private function renderSettings()
    {
        $url = $this->getActionUrl(self::SAVE_ACTION);
        $this->renderFormStart('post', $url);

        $this->renderFormHeader(__('Synchronization settings', I18N::DOMAIN));

        $this->renderSyncModeSetting();
        $this->renderOrderSyncFromDate();
        $this->renderPaymentSetting();
        $this->renderBackorderSetting();
        $this->renderBarcodeModeSetting();
        $this->renderCategoryOptions();

        $this->renderFormActionGroup(
            $this->getFormButton(
                __('Save settings', I18N::DOMAIN),
                'button-primary'
            )
        );

        $this->renderFormEnd();
    }

    private function getLastTaskProcessed(): string
    {
        if (WooCommerceOptions::exists(WooCommerceOptions::SUCCESS_SYNC_RUN)) {
            $date = WooCommerceOptions::get(WooCommerceOptions::SUCCESS_SYNC_RUN);

            return date_create($date)->format('Y-m-d H:i:s');
        } else {
            if (WooCommerceOptions::exists(WooCommerceOptions::LAST_SYNC_RUN)) {
                return __('No tasks processed (successfully) yet, but the cron is running');
            }
        }

        return __('Never, please check the "cron tab" notice.', I18N::DOMAIN);
    }

    private function getLastWebhookReceived(): string
    {
        global $wpdb;

        $hookDescription = __('Never', I18N::DOMAIN);

        $select = WebhookLogModel::getSelectHelper()
            ->cols(['date_updated'])
            ->orderBy(['date_updated DESC'])
            ->limit(1);

        $lastHook = $wpdb->get_row(WebhookLogModel::prepareQuery($select));
        if ($lastHook) {
            $date = $lastHook->date_updated;
            $hookDescription = date_create($date)->format('Y-m-d H:i:s');
        }

        return $hookDescription;
    }

    public function disconnectAction()
    {
        StoreKeeperOptions::disconnect();
        wp_redirect(remove_query_arg('action'));
    }

    public function saveAction()
    {
        $payment = StoreKeeperOptions::getConstant(StoreKeeperOptions::PAYMENT_GATEWAY_ACTIVATED);
        $backorder = StoreKeeperOptions::getConstant(StoreKeeperOptions::NOTIFY_ON_BACKORDER);
        $mode = StoreKeeperOptions::getConstant(StoreKeeperOptions::SYNC_MODE);
        $orderSyncFromDate = StoreKeeperOptions::getConstant(StoreKeeperOptions::ORDER_SYNC_FROM_DATE);
        $barcode = StoreKeeperOptions::getConstant(StoreKeeperOptions::BARCODE_MODE);
        $categoryHtml = StoreKeeperOptions::getConstant(StoreKeeperOptions::CATEGORY_DESCRIPTION_HTML);

        $data = [
            $payment => 'on' === sanitize_key($_POST[$payment]) ? 'yes' : 'no',
            $backorder => 'on' === sanitize_key($_POST[$backorder]) ? 'yes' : 'no',
            $categoryHtml => 'on' === sanitize_key($_POST[$categoryHtml]) ? 'yes' : 'no',
        ];
        if (!empty($_POST[$mode])) {
            $data[$mode] = sanitize_key($_POST[$mode]);
        } else {
            $data[$mode] = StoreKeeperOptions::SYNC_MODE_FULL_SYNC;
        }

        if (!empty($_POST[$orderSyncFromDate])) {
            $data[$orderSyncFromDate] = sanitize_key($_POST[$orderSyncFromDate]);
        }

        if (!empty($_POST[$barcode])) {
            $data[$barcode] = sanitize_key($_POST[$barcode]);
        } else {
            $data[$barcode] = StoreKeeperOptions::BARCODE_META_FALLBACK;
        }

        foreach ($data as $key => $value) {
            update_option($key, $value);
        }

        wp_redirect(remove_query_arg('action'));
    }

    private function renderSyncModeSetting(): void
    {
        $full = __('Full sync', I18N::DOMAIN);
        $order = __('Order only', I18N::DOMAIN);
        $product = __('Products only', I18N::DOMAIN);
        $none = __('No sync', I18N::DOMAIN);

        $fullDescription = esc_html__(
            'Products, categories, labels/tags, attributes with options, coupons, orders and customers are being synced',
            I18N::DOMAIN
        );
        $orderDescription = esc_html__(
            'Orders and customers are being exported, order status and product stock with matching skus are being imports',
            I18N::DOMAIN
        );
        $productDescription = esc_html__(
            'Products, categories, labels/tags, attributes with options, and coupons are being synced',
            I18N::DOMAIN
        );
        $noneDescription = esc_html__(
            'Nothing will be synced',
            I18N::DOMAIN
        );

        $description = <<<HTML
<strong>$full:</strong> $fullDescription</br>
<strong>$order:</strong> $orderDescription</br>
<strong>$product:</strong> $productDescription</br>
<strong>$none:</strong> $noneDescription
HTML;

        $options = [];
        $options[StoreKeeperOptions::SYNC_MODE_FULL_SYNC] = $full;
        $options[StoreKeeperOptions::SYNC_MODE_ORDER_ONLY] = $order;
        $options[StoreKeeperOptions::SYNC_MODE_PRODUCT_ONLY] = $product;
        $options[StoreKeeperOptions::SYNC_MODE_NONE] = $none;

        $name = StoreKeeperOptions::getConstant(StoreKeeperOptions::SYNC_MODE);
        $this->renderFormGroup(
            __('Synchronization mode', I18N::DOMAIN),
            $this->getFormSelect(
                $name,
                $options,
                StoreKeeperOptions::getSyncMode()
            )
        );

        $this->renderFormGroup('', $description);
    }

    private function renderBarcodeModeSetting(): void
    {
        $options = StoreKeeperOptions::getBarcodeOptions();
        $name = StoreKeeperOptions::getConstant(StoreKeeperOptions::BARCODE_MODE);
        $this->renderFormGroup(
            __('Barcode meta key', I18N::DOMAIN),
            $this->getFormSelect(
                $name,
                $options,
                StoreKeeperOptions::getBarcodeMode()
            )
        );

        $description = esc_html__(
            'Changing this settings allows to use various EAN, barcode plugins. After changing this setting all products need to be synchronized again.',
            I18N::DOMAIN
        );
        $this->renderFormGroup('', $description);
    }

    private function renderOrderSyncFromDate(): void
    {
        $orderSyncFromDateName = StoreKeeperOptions::getConstant(StoreKeeperOptions::ORDER_SYNC_FROM_DATE);

        $this->renderFormGroup(
            __('Order sync from date', I18N::DOMAIN),
            $this->getFormInput($orderSyncFromDateName, '', StoreKeeperOptions::get($orderSyncFromDateName, ''), '', 'date')
        );

        $description = esc_html__(
            'Order created before the date set will not be synchronized to backoffice.',
            I18N::DOMAIN
        );
        $this->renderFormGroup('', $description);
    }

    private function renderPaymentSetting(): void
    {
        $paymentName = StoreKeeperOptions::getConstant(StoreKeeperOptions::PAYMENT_GATEWAY_ACTIVATED);
        $extraInfo = '';
        if (!StoreKeeperOptions::isPaymentSyncEnabled()) {
            $extraInfo = '<br><small>'.__('Payments are disabled in currently selected Synchronization mode').'</small>';
        }
        $this->renderFormGroup(
            __('Activate StoreKeeper payments', I18N::DOMAIN),
            $this->getFormCheckbox(
                $paymentName,
                'yes' === StoreKeeperOptions::get($paymentName),
                StoreKeeperOptions::isPaymentSyncEnabled() ? '' : 'disabled',
            ).' '.__(
                'When checked, active webshop payment methods from your StoreKeeper backoffice are added to your webshop\'s checkout',
                I18N::DOMAIN
            ).$extraInfo
        );
    }

    private function renderCategoryOptions(): void
    {
        $name = StoreKeeperOptions::getConstant(StoreKeeperOptions::CATEGORY_DESCRIPTION_HTML);
        $this->renderFormGroup(
            __('Import category description as HTML', I18N::DOMAIN),
            $this->getFormCheckbox(
                $name,
                StoreKeeperOptions::getBoolOption($name, false)
            ).' '.__(
                'It will import the category descriptions as html, otherwise plain text. It requires a theme support for rendering it correctly.',
                I18N::DOMAIN
            )
        );
    }

    private function renderBackorderSetting(): void
    {
        $backorderName = StoreKeeperOptions::getConstant(StoreKeeperOptions::NOTIFY_ON_BACKORDER);
        $this->renderFormGroup(
            __('Notify when backorderable', I18N::DOMAIN),
            $this->getFormCheckbox(
                $backorderName,
                'yes' === StoreKeeperOptions::get($backorderName)
            ).' '.__(
                "When checked, imported or updated products will have the backorder status set to 'Allow, but notify customer', else I will be set to 'Allow'",
                I18N::DOMAIN
            )
        );
    }
}
