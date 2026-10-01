<?php
namespace ProcessWire;

/**
 * WireSettings
 *
 * Centralized settings management for ProcessWire 3.x.
 *
 * Eliminates frontend page tree clutter by hosting the settings data page inside
 * the hidden admin hierarchy, while providing a dedicated top-level admin menu link
 * and exposing a global $settings API variable in all frontend templates.
 *
 * @author Markus Thomas
 * @version 1.0.2
 * @license MIT
 */
class WireSettings extends WireData implements Module, ConfigurableModule {

    /**
     * Settings Page Cache
     * @var Page|null
     */
    protected ?Page $settingsPage = null;

    /**
     * Admin Menu Page Cache
     * @var Page|null
     */
    protected ?Page $adminPage = null;

    /**
     * Initialize the module
     *
     * Registers hooks early.
     *
     * @return void
     */
    public function init() {
        // 1. Hook ProcessPageEdit to route the admin page to our settings data page and clean editor UI
        $this->addHookBefore('ProcessPageEdit::init', $this, 'hookProcessPageEditInit');
        $this->addHookBefore('ProcessPageEdit::buildFormSettings', $this, 'hookProcessPageEditBuildFormSettings');
        $this->addHookBefore('ProcessPageEdit::buildFormDelete', $this, 'hookProcessPageEditBuildFormDelete');
        $this->addHookBefore('ProcessPageEdit::buildFormChildren', $this, 'hookProcessPageEditBuildFormChildren');
        $this->addHookAfter('ProcessPageEdit::buildForm', $this, 'hookProcessPageEditBuildForm');

        // 2. Grant edit access to users with 'wire-settings' permission
        $this->addHookAfter('Page::editable', $this, 'hookPageEditable');

        // 3. Keep admin navigation page title synchronized when module config is saved
        $this->addHookAfter('Modules::saveModuleConfigData', $this, 'hookSaveModuleConfigData');
    }

    /**
     * Ready logic (executes when the API and current page are 100% ready)
     *
     * Exposes $settings to frontend templates, ensures title sync, and handles asset enqueuing.
     *
     * @return void
     */
    public function ready() {
        // 1. Synchronize admin navigation menu title if changed in module config
        $this->syncAdminPageTitle();

        // 2. Expose global $settings API variable to frontend templates
        $this->exposeSettingsVariable();

        // 3. Automatically relocate settings page out of the normal page tree if enabled
        if ($this->get('hide_from_page_tree', 1) && $this->wire('user') && $this->wire('user')->isLoggedin()) {
            $this->moveSettingsPageUnderAdmin();
        }

        // 4. Enqueue CSS on admin settings page
        $adminPage = $this->getAdminPage();
        $currentPage = $this->wire('page');
        if ($adminPage && $currentPage && (int) $currentPage->id === (int) $adminPage->id) {
            $this->wire('input')->get->id = $this->getSettingsPage() ? $this->getSettingsPage()->id : null;

            $cssPath = $this->wire('config')->paths->WireSettings . 'WireSettings.css';
            $cssUrl = $this->wire('config')->urls->WireSettings . 'WireSettings.css';
            if (file_exists($cssPath)) {
                $this->wire('config')->styles->add($cssUrl . '?v=' . filemtime($cssPath));
            }
        }
    }

    /**
     * Synchronize the admin menu page title with the configured 'admin_title'
     *
     * Ensures changing the title in module settings immediately updates the top admin navigation.
     *
     * @param string|null $newTitle Optional new title to set
     * @return void
     */
    public function syncAdminPageTitle(?string $newTitle = null): void {
        try {
            $adminPage = $this->getAdminPage();
            if (!$adminPage || !$adminPage->id) return;

            $title = $newTitle !== null ? trim($newTitle) : trim((string) ($this->get('admin_title') ?: 'Settings'));
            if (!strlen($title)) $title = 'Settings';

            if ((string) $adminPage->title !== $title) {
                $adminPage->of(false);
                $adminPage->title = $title;
                $adminPage->save('title');
            }
        } catch (\Throwable $e) {
            // Silently ignore sync errors
        }
    }

    /**
     * Hook after Modules::saveModuleConfigData
     *
     * Immediately synchronizes admin page title when module config is saved.
     *
     * @param HookEvent $event
     * @return void
     */
    public function hookSaveModuleConfigData(HookEvent $event): void {
        $className = $event->arguments(0);
        if ($className === $this->className()) {
            $data = $event->arguments(1);
            if (is_array($data) && isset($data['admin_title'])) {
                $this->syncAdminPageTitle((string) $data['admin_title']);
            }
        }
    }

    /**
     * Check if the current request or ProcessPageEdit instance targets the settings page
     *
     * @param ProcessPageEdit|null $ppe
     * @return bool
     */
    protected function isSettingsEdit(?ProcessPageEdit $ppe = null): bool {
        $adminPage = $this->getAdminPage();
        $currentPage = $this->wire('page');
        if ($adminPage && $currentPage && (int) $currentPage->id === (int) $adminPage->id) {
            return true;
        }

        $settingsPage = $this->getSettingsPage();
        if ($settingsPage && $settingsPage->id) {
            if ($ppe) {
                $editingPage = $ppe->getPage();
                if ($editingPage && (int) $editingPage->id === (int) $settingsPage->id) {
                    return true;
                }
            }

            $inputId = (int) $this->wire('input')->get('id');
            if ($inputId && $inputId === (int) $settingsPage->id) {
                return true;
            }
        }

        return false;
    }

    /**
     * Expose the settings page as a global API variable
     *
     * Enables direct access via $settings->field_name in frontend templates,
     * as well as wire('settings')->field_name and $wire->settings.
     *
     * @return void
     */
    protected function exposeSettingsVariable(): void {
        try {
            $settingsPage = $this->getSettingsPage();
            if ($settingsPage && $settingsPage->id) {
                $varName = $this->get('api_var_name') ?: 'settings';

                // Ensure outputFormatting is enabled when accessed on the frontend
                $currentPage = $this->wire('page');
                if ($currentPage && $currentPage->id && $currentPage->template && $currentPage->template->name !== 'admin') {
                    $settingsPage->of(true);
                }

                // Expose globally to ProcessWire Fuel container ($settings in templates)
                $this->wire($varName, $settingsPage);
            }
        } catch (\Throwable $e) {
            // Suppress any runtime errors during variable exposure
        }
    }

    /**
     * Hook before ProcessPageEdit::init
     *
     * Directs ProcessPageEdit to load the settings data page when accessing the admin settings page.
     *
     * @param HookEvent $event
     * @return void
     * @throws WirePermissionException
     */
    public function hookProcessPageEditInit(HookEvent $event): void {
        $adminPage = $this->getAdminPage();
        if (!$adminPage || !$adminPage->id) return;

        $currentPage = $this->wire('page');
        if ($currentPage && (int) $currentPage->id === (int) $adminPage->id) {
            // Check user access
            $user = $this->wire('user');
            if (!$user->hasPermission('wire-settings') && !$user->isSuperuser()) {
                throw new WirePermissionException($this->_('You do not have permission to manage global settings.'));
            }

            $settingsPage = $this->getSettingsPage();
            if ($settingsPage && $settingsPage->id) {
                $this->wire('input')->get->id = $settingsPage->id;

                /** @var ProcessPageEdit $ppe */
                $ppe = $event->object;
                $ppe->setPage($settingsPage);
            }
        }
    }

    /**
     * Hook before ProcessPageEdit::buildFormSettings
     *
     * Replaces core "Settings" tab generation with an empty wrapper when hide_system_tabs is enabled.
     *
     * @param HookEvent $event
     * @return void
     */
    public function hookProcessPageEditBuildFormSettings(HookEvent $event): void {
        if (!$this->get('hide_system_tabs', 1)) return;

        /** @var ProcessPageEdit $ppe */
        $ppe = $event->object;
        if ($this->isSettingsEdit($ppe)) {
            $event->replace = true;
            $event->return = $this->wire(new InputfieldWrapper());
        }
    }

    /**
     * Hook before ProcessPageEdit::buildFormDelete
     *
     * Replaces core "Delete" tab generation with an empty wrapper when hide_system_tabs is enabled.
     *
     * @param HookEvent $event
     * @return void
     */
    public function hookProcessPageEditBuildFormDelete(HookEvent $event): void {
        if (!$this->get('hide_system_tabs', 1)) return;

        /** @var ProcessPageEdit $ppe */
        $ppe = $event->object;
        if ($this->isSettingsEdit($ppe)) {
            $event->replace = true;
            $event->return = $this->wire(new InputfieldWrapper());
        }
    }

    /**
     * Hook before ProcessPageEdit::buildFormChildren
     *
     * Replaces core "Children" tab generation with an empty wrapper when hide_system_tabs is enabled.
     *
     * @param HookEvent $event
     * @return void
     */
    public function hookProcessPageEditBuildFormChildren(HookEvent $event): void {
        if (!$this->get('hide_system_tabs', 1)) return;

        /** @var ProcessPageEdit $ppe */
        $ppe = $event->object;
        if ($this->isSettingsEdit($ppe)) {
            $event->replace = true;
            $event->return = $this->wire(new InputfieldWrapper());
        }
    }

    /**
     * Hook after ProcessPageEdit::buildForm
     *
     * Customizes headline, cleans breadcrumbs, hides unnecessary system tabs,
     * and displays a quick template configuration shortcut for superusers.
     *
     * @param HookEvent $event
     * @return void
     */
    public function hookProcessPageEditBuildForm(HookEvent $event): void {
        $adminPage = $this->getAdminPage();
        $settingsPage = $this->getSettingsPage();
        if (!$adminPage || !$settingsPage) return;

        /** @var ProcessPageEdit $ppe */
        $ppe = $event->object;
        if (!$this->isSettingsEdit($ppe)) return;

        /** @var InputfieldWrapper $form */
        $form = $event->return;
        if (!$form) return;

        // Add CSS hook class
        $form->addClass('wire-settings-page');

        // 1. Set clean headline and breadcrumbs
        $adminTitle = $this->get('admin_title') ?: $this->_('Settings');
        $this->wire('processHeadline', $adminTitle);
        $breadcrumbs = $this->wire('breadcrumbs');
        if ($breadcrumbs) {
            $breadcrumbs->reset();
            $adminUrl = $this->wire('config')->urls->admin;
            $breadcrumbs->add(new Breadcrumb($adminUrl, $this->_('Admin')));
        }

        // 2. Hide core ProcessPageEdit system tabs to keep editor clutter-free
        if ($this->get('hide_system_tabs', 1)) {
            $systemTabNames = [
                'ProcessPageEditSettings',
                'ProcessPageEditDelete',
                'ProcessPageEditChildren',
                'ProcessPageViewPass',
                'settings',
                'delete',
                'children',
            ];

            // A) Remove from ProcessPageEdit internal tabs array
            if (method_exists($ppe, 'removeTab')) {
                foreach ($systemTabNames as $tabId) {
                    $ppe->removeTab($tabId);
                }
            }

            // B) Remove wrappers directly from form children
            foreach ($form->children() as $child) {
                $childId = (string) $child->attr('id');
                $childName = (string) ($child->attr('name') ?: $child->name);

                foreach ($systemTabNames as $sysName) {
                    if ($childId === $sysName || $childName === $sysName || str_ends_with($childId, $sysName)) {
                        $form->remove($child);
                        break;
                    }
                }
            }
        }

        // 3. Superuser Shortcut: Quick link to configure template fields in Setup > Templates
        if ($this->get('show_template_shortcut', 1) && $this->wire('user')->isSuperuser()) {
            $template = $settingsPage->template;
            if ($template && $template->id) {
                $editTemplateUrl = $this->wire('config')->urls->admin . "setup/template/edit?id={$template->id}";

                /** @var InputfieldMarkup $shortcut */
                $shortcut = $this->wire('modules')->get('InputfieldMarkup');
                $shortcut->attr('id', 'wire_settings_template_shortcut');
                $shortcut->label = $this->_('Settings Fields Configuration');
                $shortcut->collapsed = Inputfield::collapsedBlank;

                $shortcut->value = sprintf(
                    '<div class="uk-alert uk-alert-primary uk-margin-small-bottom" style="display:flex;align-items:center;justify-content:space-between;padding:10px 16px;">
                        <div>
                            <span uk-icon="icon: info; ratio: 0.9" class="uk-margin-small-right"></span>
                            <span>%s: <code>%s</code></span>
                        </div>
                        <a href="%s" class="uk-button uk-button-default uk-button-small" style="background:#ffffff;color:#333;">
                            <span uk-icon="icon: cog; ratio: 0.8" class="uk-margin-small-right"></span>%s
                        </a>
                    </div>',
                    $this->_('Configure fields and tabs on template'),
                    $this->wire('sanitizer')->entities($template->name),
                    $editTemplateUrl,
                    $this->_('Edit Template Fields')
                );

                $form->prepend($shortcut);
            }
        }
    }

    /**
     * Hook after Page::editable
     *
     * Ensures users with 'wire-settings' permission can edit the settings page,
     * even if it has a system or hidden status.
     *
     * @param HookEvent $event
     * @return void
     */
    public function hookPageEditable(HookEvent $event): void {
        $page = $event->object;
        $settingsPage = $this->getSettingsPage();

        if ($settingsPage && (int) $page->id === (int) $settingsPage->id) {
            $user = $this->wire('user');
            if ($user->hasPermission('wire-settings') || $user->isSuperuser()) {
                $event->return = true;
            }
        }
    }

    /**
     * Retrieve the settings data page safely using multi-strategy detection
     *
     * @return Page|null
     */
    public function getSettingsPage(): ?Page {
        if ($this->settingsPage !== null) {
            return $this->settingsPage;
        }

        $pages = $this->wire('pages');
        $templates = $this->wire('templates');
        if (!$pages || !$templates) return null;

        $templateName = $this->get('template_name') ?: 'settings';

        // Strategy 1: Check configured settings_page_id
        $pageId = (int) $this->get('settings_page_id');
        if ($pageId > 0) {
            try {
                $page = $pages->get($pageId);
                if ($page && $page->id && (int) $page->id !== 2) {
                    $this->settingsPage = $page;
                    return $this->settingsPage;
                }
            } catch (\Throwable $e) {}
        }

        // Strategy 2: Search by template name
        $template = $templates->get($templateName);
        if ($template) {
            try {
                $page = $pages->get("template={$template->name}, id!=2, include=all");
                if ($page && $page->id) {
                    $this->settingsPage = $page;
                    return $this->settingsPage;
                }
            } catch (\Throwable $e) {}
        }

        // Strategy 3: Search by path '/settings/'
        try {
            $page = $pages->get("/settings/");
            if ($page && $page->id && (int) $page->id !== 2) {
                $this->settingsPage = $page;
                return $this->settingsPage;
            }
        } catch (\Throwable $e) {}

        // Strategy 4: Search by name 'settings' or 'einstellungen'
        try {
            $page = $pages->get("name=settings|einstellungen, id!=2, include=all");
            if ($page && $page->id) {
                $this->settingsPage = $page;
                return $this->settingsPage;
            }
        } catch (\Throwable $e) {}

        return null;
    }

    /**
     * Retrieve the admin menu page (/admin/settings/)
     *
     * @return Page|null
     */
    public function getAdminPage(): ?Page {
        if ($this->adminPage !== null) {
            return $this->adminPage;
        }

        try {
            $pages = $this->wire('pages');
            if (!$pages) return null;

            $adminRoot = $pages->get(2);
            if (!$adminRoot || !$adminRoot->id) return null;

            $page = $pages->get("parent=$adminRoot, name=settings, include=all");
            if ($page && $page->id) {
                $this->adminPage = $page;
                return $this->adminPage;
            }
        } catch (\Throwable $e) {
            return null;
        }

        return null;
    }

    /**
     * Move the settings page under /admin/settings/ so it disappears from the normal page tree
     *
     * @return bool
     */
    public function moveSettingsPageUnderAdmin(): bool {
        $settingsPage = $this->getSettingsPage();
        $adminPage = $this->getAdminPage();

        if (!$settingsPage || !$settingsPage->id || !$adminPage || !$adminPage->id) {
            return false;
        }

        // Already relocated under admin
        if ((int) $settingsPage->parent_id === (int) $adminPage->id) {
            return true;
        }

        try {
            $template = $settingsPage->template;
            $adminTemplate = $adminPage->template;

            // 1. Ensure template family allows admin as parent
            if ($template && $adminTemplate) {
                $pTemplates = $template->parentTemplates;
                if (is_array($pTemplates) && count($pTemplates) > 0 && !in_array($adminTemplate->id, $pTemplates)) {
                    $pTemplates[] = $adminTemplate->id;
                    $template->parentTemplates = $pTemplates;
                    $template->save();
                }

                $cTemplates = $adminTemplate->childTemplates;
                if (is_array($cTemplates) && count($cTemplates) > 0 && !in_array($template->id, $cTemplates)) {
                    $cTemplates[] = $template->id;
                    $adminTemplate->childTemplates = $cTemplates;
                    $adminTemplate->save();
                }
            }

            // 2. Relocate settings page under admin
            $settingsPage->of(false);
            $settingsPage->parent = $adminPage;
            $settingsPage->name = 'data';
            $settingsPage->status = Page::statusHidden | Page::statusSystem;
            $settingsPage->save();

            // Clear cache
            $this->settingsPage = $settingsPage;

            // Update saved ID
            $this->wire('modules')->saveConfig($this->className(), [
                'settings_page_id' => $settingsPage->id,
            ]);

            $this->message(sprintf(
                $this->_('WireSettings: Settings page "%s" (ID: %d) successfully relocated under admin. It is now completely removed from the normal page tree!'),
                $settingsPage->title,
                $settingsPage->id
            ));

            return true;
        } catch (\Throwable $e) {
            return false;
        }
    }

    /**
     * Module Installation Routine
     *
     * Detects existing templates and settings pages, preserving all fields and data.
     *
     * @return void
     */
    public function ___install(): void {
        $templateName = $this->get('template_name') ?: 'settings';

        // 1. Detect or Create the Settings Template
        $template = $this->wire('templates')->get($templateName);
        if ($template) {
            $this->message(sprintf(
                $this->_('WireSettings: Existing template "%s" detected! Preserved all fields and configuration.'),
                $templateName
            ));
        } else {
            try {
                $fieldgroup = new Fieldgroup();
                $fieldgroup->name = $templateName;

                $titleField = $this->wire('fields')->get('title');
                if ($titleField) {
                    $fieldgroup->add($titleField);
                }
                $fieldgroup->save();

                $template = new Template();
                $template->name = $templateName;
                $template->fieldgroup = $fieldgroup;
                $template->slashUrls = 1;
                $template->save();

                $this->message(sprintf(
                    $this->_('WireSettings: Created new template "%s" with default title field.'),
                    $templateName
                ));
            } catch (\Throwable $e) {
                $this->error($this->_('WireSettings: Could not create template: ') . $e->getMessage());
                return;
            }
        }

        // 2. Create or verify Top-Level Admin Navigation Link (/admin/settings/)
        $adminRoot = $this->wire('pages')->get(2);
        $adminPage = $this->wire('pages')->get("parent=$adminRoot, name=settings, include=all");

        if (!$adminPage || !$adminPage->id) {
            try {
                $adminPage = new Page();
                $adminPage->template = 'admin';
                $adminPage->parent = $adminRoot;
                $adminPage->name = 'settings';
                $adminPage->title = $this->get('admin_title') ?: 'Settings';
                $adminPage->process = 'ProcessPageEdit';
                $adminPage->save();

                $this->message(sprintf(
                    $this->_('WireSettings: Created admin navigation link at "%s".'),
                    $adminPage->path
                ));
            } catch (\Throwable $e) {
                $this->error($this->_('Could not create admin navigation page: ') . $e->getMessage());
            }
        } else {
            if ($adminPage->process != 'ProcessPageEdit') {
                $adminPage->process = 'ProcessPageEdit';
                $adminPage->save();
            }
        }

        // 3. Detect Existing Settings Page
        $settingsPage = $this->getSettingsPage();
        if ($settingsPage && $settingsPage->id) {
            $this->message(sprintf(
                $this->_('WireSettings: Successfully detected existing settings page "%s" (ID: %d, Path: %s).'),
                $settingsPage->title,
                $settingsPage->id,
                $settingsPage->path
            ));

            $this->wire('modules')->saveConfig($this->className(), [
                'settings_page_id' => $settingsPage->id,
                'template_name' => $settingsPage->template->name,
                'api_var_name' => $this->get('api_var_name') ?: 'settings',
                'admin_title' => $this->get('admin_title') ?: 'Settings',
                'hide_system_tabs' => 1,
                'show_template_shortcut' => 1,
            ]);
        } else if ($template) {
            // Create a new settings page under Home
            try {
                $settingsPage = new Page();
                $settingsPage->template = $template;
                $settingsPage->parent = $this->wire('pages')->get(1); // Home
                $settingsPage->name = 'settings';
                $settingsPage->title = $this->get('admin_title') ?: 'Settings';
                $settingsPage->status = Page::statusHidden;
                $settingsPage->save();

                $this->message(sprintf(
                    $this->_('WireSettings: Created new settings page (ID: %d).'),
                    $settingsPage->id
                ));

                $this->wire('modules')->saveConfig($this->className(), [
                    'settings_page_id' => $settingsPage->id,
                    'template_name' => $templateName,
                    'api_var_name' => $this->get('api_var_name') ?: 'settings',
                    'admin_title' => $this->get('admin_title') ?: 'Settings',
                    'hide_system_tabs' => 1,
                    'show_template_shortcut' => 1,
                ]);
            } catch (\Throwable $e) {
                $this->error($this->_('WireSettings: Could not create settings page: ') . $e->getMessage());
            }
        }

        // 4. Ensure permission 'wire-settings' exists
        $permission = $this->wire('permissions')->get('wire-settings');
        if (!$permission || !$permission->id) {
            try {
                $permission = $this->wire('permissions')->add('wire-settings');
                $permission->title = $this->_('Manage global website settings');
                $permission->save();
                $this->message($this->_('WireSettings: Created permission "wire-settings".'));
            } catch (\Throwable $e) {
                // Ignore if already registered
            }
        }

        // 5. Relocate settings page under admin to eliminate it from the normal page tree
        if ($this->get('hide_from_page_tree', 1)) {
            $this->moveSettingsPageUnderAdmin();
        }
    }

    /**
     * Module Uninstall Routine
     *
     * Cleanly tears down permissions and the admin menu link without deleting custom template fields.
     *
     * @return void
     */
    public function ___uninstall(): void {
        try {
            $settingsPage = $this->getSettingsPage();
            $adminPage = $this->getAdminPage();

            // If settings page was moved under admin, safely restore it under Home before deleting adminPage
            if ($settingsPage && $settingsPage->id && $adminPage && (int) $settingsPage->parent_id === (int) $adminPage->id) {
                $settingsPage->of(false);
                $settingsPage->parent = $this->wire('pages')->get(1); // Home
                $settingsPage->name = 'settings';
                $settingsPage->status = Page::statusHidden;
                $settingsPage->save();
                $this->message($this->_('WireSettings: Settings page restored to Home before uninstall.'));
            }

            if ($adminPage && $adminPage->id) {
                $this->wire('pages')->delete($adminPage, true);
                $this->message($this->_('WireSettings: Removed admin navigation link.'));
            }

            $permission = $this->wire('permissions')->get('wire-settings');
            if ($permission && $permission->id) {
                $this->wire('permissions')->delete($permission);
                $this->message($this->_('WireSettings: Removed permission "wire-settings".'));
            }
        } catch (\Throwable $e) {
            $this->error($this->_('WireSettings: Uninstall notice: ') . $e->getMessage());
        }

        // Note: Template 'settings' and its fields are intentionally preserved
        // to safeguard customer data against accidental removal.
        $this->warning($this->_('WireSettings: Template and field data were preserved to prevent data loss. You can remove them in Setup > Templates if desired.'));
    }
}
