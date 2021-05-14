<?php

namespace StoreKeeper\WooCommerce\B2C\Backoffice\Pages;

use StoreKeeper\WooCommerce\B2C\I18N;

abstract class AbstractPage extends AbstractPageLike
{
    protected $slug = '';

    final public function getSlug(): string
    {
        return "storekeeper-$this->slug";
    }

    final public function getActionName(): string
    {
        return "load-storekeeper-page-$this->slug";
    }

    public $title = '';

    /** @var AbstractTab[] */
    private $tabs = [];

    private function getCurrentTab(): ?AbstractTab
    {
        $slug = $_REQUEST['tab'] ?? '';
        $tab = array_key_exists($slug, $this->tabs) ?
            $this->tabs[$slug] : current($this->tabs);

        if ($tab) {
            return $tab;
        }

        return null;
    }

    /** @return AbstractTab[] Returns the tags required for this page */
    abstract protected function getTabs(): array;

    public function __construct(string $title, string $slug)
    {
        $this->slug = $slug;
        $this->title = $title;
        foreach ($this->getTabs() as $tab) {
            $this->tabs[$tab->slug] = $tab;
        }
    }

    protected function getStylePaths(): array
    {
        return array_merge(
            [
                plugin_dir_url(__FILE__).'/../../static/default.page.css',
            ]
        );
    }

    public function initialize()
    {
        $this->triggerAction();
        $this->register();
        $this->render();
    }

    private function triggerAction()
    {
        do_action($this->getActionName());
    }

    final public function register(): void
    {
        parent::register();

        if ($tab = $this->getCurrentTab()) {
            $tab->register();
        }
    }

    final public function render(): void
    {
        $page = $_REQUEST['page'] ?? '';
        echo "<div class='storekeeper-page storekeeper-page-$page'>";

        $this->renderTitle();

        $this->renderTabs();

        $this->renderTab();

        echo '</div>';
    }

    private function renderTitle(): void
    {
        $title = __('StoreKeeper Sync Plugin', I18N::DOMAIN);
        if ($this->title) {
            $title .= ' - '.$this->title;
        }
        $title = esc_html($title);
        echo <<<HTML
<h1>$title</h1>
HTML;
    }

    private function renderTabs()
    {
        global $pagenow;
        $tabHtml = '';

        if (count($this->tabs) > 1) {
            $currentSlug = $_REQUEST['tab'] ?? '';
            foreach ($this->tabs as $slug => $tab) {
                $url = add_query_arg('page', $_REQUEST['page'], $pagenow);
                if ('' !== $slug) {
                    $url = add_query_arg('tab', $slug, $url);
                }

                $className = $currentSlug === $slug ? 'nav-tab-active' : '';
                $title = esc_html($tab->title);
                $tabHtml .= "<a href='$url' class='nav-tab $className'>$title</a>&nbsp;";
            }
        }

        echo <<<HTML
<nav class="nav-tab-wrapper woo-nav-tab-wrapper">
    $tabHtml
</nav>
HTML;
    }

    private function renderTab(): void
    {
        if ($tab = $this->getCurrentTab()) {
            echo "<div class='storekeeper-tab storekeeper-tab-$tab->slug'>";

            $tab->render();

            echo '</div>';
        } else {
            $text = __('No tabs set for this page', I18N::DOMAIN);
            echo "<h1 style='text-align: center'>$text</h1>";
        }
    }
}
