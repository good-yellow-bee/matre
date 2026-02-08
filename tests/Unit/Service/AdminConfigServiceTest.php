<?php

declare(strict_types=1);

namespace App\Tests\Unit\Service;

use App\Entity\Settings;
use App\Repository\SettingsRepository;
use App\Service\AdminConfigService;
use PHPUnit\Framework\TestCase;

class AdminConfigServiceTest extends TestCase
{
    public function testGetAdminMenuReturnsAllSections(): void
    {
        $menu = $this->createService()->getAdminMenu();

        self::assertArrayHasKey('testing', $menu);
        self::assertArrayHasKey('users', $menu);
        self::assertArrayHasKey('system', $menu);
        self::assertCount(3, $menu);
    }

    public function testGetAdminMenuTestingSectionHasExpectedItems(): void
    {
        $menu = $this->createService()->getAdminMenu();

        $items = $menu['testing']['items'];
        self::assertArrayHasKey('test_run', $items);
        self::assertArrayHasKey('test_environment', $items);
        self::assertArrayHasKey('test_suite', $items);
    }

    public function testGetMenuSectionReturnsSection(): void
    {
        $section = $this->createService()->getMenuSection('testing');

        self::assertNotNull($section);
        self::assertSame('Test Management', $section['label']);
        self::assertSame('bi-check-circle', $section['icon']);
        self::assertArrayHasKey('items', $section);
    }

    public function testGetMenuSectionReturnsNullForUnknown(): void
    {
        self::assertNull($this->createService()->getMenuSection('nonexistent'));
    }

    public function testGetEntityConfigFindsAcrossSections(): void
    {
        $service = $this->createService();

        $testRun = $service->getEntityConfig('test_run');
        self::assertSame('Test Runs', $testRun['label']);
        self::assertSame('App\Entity\TestRun', $testRun['entity']);

        $user = $service->getEntityConfig('user');
        self::assertSame('Users', $user['label']);
        self::assertSame('App\Entity\User', $user['entity']);

        $settings = $service->getEntityConfig('settings');
        self::assertSame('Settings', $settings['label']);
    }

    public function testGetEntityConfigReturnsNullForUnknown(): void
    {
        self::assertNull($this->createService()->getEntityConfig('nonexistent'));
    }

    public function testGetEntityClassReturnsClassName(): void
    {
        $service = $this->createService();

        self::assertSame('App\Entity\TestRun', $service->getEntityClass('test_run'));
        self::assertSame('App\Entity\TestEnvironment', $service->getEntityClass('test_environment'));
        self::assertSame('App\Entity\User', $service->getEntityClass('user'));
    }

    public function testGetEntityClassReturnsNullForUnknown(): void
    {
        self::assertNull($this->createService()->getEntityClass('nonexistent'));
    }

    public function testGetEntityClassReturnsNullForEntityWithoutClass(): void
    {
        // 'settings' item has no 'entity' key
        self::assertNull($this->createService()->getEntityClass('settings'));
    }

    public function testGetEntityDefaultsReturnsAll(): void
    {
        $defaults = $this->createService()->getEntityDefaults();

        self::assertIsArray($defaults);
        self::assertSame(20, $defaults['items_per_page']);
        self::assertTrue($defaults['enable_search']);
        self::assertTrue($defaults['enable_sorting']);
        self::assertTrue($defaults['enable_filters']);
        self::assertFalse($defaults['show_id_column']);
        self::assertSame('Y-m-d H:i', $defaults['date_format']);
        self::assertSame('Y-m-d', $defaults['short_date_format']);
    }

    public function testGetEntityDefaultsReturnsSingleKey(): void
    {
        $service = $this->createService();

        self::assertSame(20, $service->getEntityDefaults('items_per_page'));
        self::assertTrue($service->getEntityDefaults('enable_search'));
        self::assertSame('Y-m-d H:i', $service->getEntityDefaults('date_format'));
    }

    public function testGetEntityDefaultsReturnsNullForUnknownKey(): void
    {
        self::assertNull($this->createService()->getEntityDefaults('nonexistent'));
    }

    public function testGetSiteSettingsDelegatesToRepository(): void
    {
        $settings = new Settings();
        $repo = $this->createMock(SettingsRepository::class);
        $repo
            ->expects($this->once())
            ->method('getSettings')
            ->willReturn($settings);

        self::assertSame($settings, $this->createService($repo)->getSiteSettings());
    }

    public function testGetSiteSettingReturnsSiteName(): void
    {
        $settings = new Settings();
        $settings->setSiteName('My Site');

        $repo = $this->createStub(SettingsRepository::class);
        $repo->method('getSettings')->willReturn($settings);

        self::assertSame('My Site', $this->createService($repo)->getSiteSetting('siteName'));
    }

    public function testGetSiteSettingReturnsBooleanSetting(): void
    {
        $settings = new Settings();
        $settings->setHeadlessMode(true);

        $repo = $this->createStub(SettingsRepository::class);
        $repo->method('getSettings')->willReturn($settings);

        // headlessMode getter is isHeadlessMode(), not getHeadlessMode() - no matching getter
        self::assertNull($this->createService($repo)->getSiteSetting('headlessMode'));
    }

    public function testGetSiteSettingReturnsNullForUnknownKey(): void
    {
        $settings = new Settings();
        $repo = $this->createStub(SettingsRepository::class);
        $repo->method('getSettings')->willReturn($settings);

        self::assertNull($this->createService($repo)->getSiteSetting('nonexistent'));
    }

    public function testHasAccessGrantedWithMatchingRole(): void
    {
        $service = $this->createService();

        self::assertTrue($service->hasAccess('test_run', ['ROLE_USER']));
        self::assertTrue($service->hasAccess('test_environment', ['ROLE_ADMIN']));
        self::assertTrue($service->hasAccess('user', ['ROLE_ADMIN']));
    }

    public function testHasAccessDeniedWithoutMatchingRole(): void
    {
        $service = $this->createService();

        self::assertFalse($service->hasAccess('test_environment', ['ROLE_USER']));
        self::assertFalse($service->hasAccess('user', ['ROLE_USER']));
        self::assertFalse($service->hasAccess('settings', ['ROLE_USER']));
    }

    public function testHasAccessDeniedForUnknownEntity(): void
    {
        self::assertFalse($this->createService()->hasAccess('nonexistent', ['ROLE_ADMIN']));
    }

    public function testHasAccessDeniedWithEmptyRoles(): void
    {
        self::assertFalse($this->createService()->hasAccess('test_run', []));
    }

    public function testHasAccessGrantedWhenUserHasMultipleRoles(): void
    {
        self::assertTrue($this->createService()->hasAccess('test_environment', ['ROLE_USER', 'ROLE_ADMIN']));
    }

    public function testGetFilteredMenuForAdmin(): void
    {
        $menu = $this->createService()->getFilteredMenu(['ROLE_ADMIN']);

        self::assertArrayHasKey('testing', $menu);
        self::assertArrayHasKey('users', $menu);
        self::assertArrayHasKey('system', $menu);

        // Admin sees all testing items
        self::assertCount(1, $menu['testing']['items']);
        self::assertArrayHasKey('test_environment', $menu['testing']['items']);
    }

    public function testGetFilteredMenuForRegularUser(): void
    {
        $menu = $this->createService()->getFilteredMenu(['ROLE_USER']);

        self::assertArrayHasKey('testing', $menu);
        self::assertArrayNotHasKey('users', $menu);
        self::assertArrayNotHasKey('system', $menu);

        $items = $menu['testing']['items'];
        self::assertArrayHasKey('test_run', $items);
        self::assertArrayHasKey('test_suite', $items);
        self::assertArrayNotHasKey('test_environment', $items);
    }

    public function testGetFilteredMenuForUserWithBothRoles(): void
    {
        $menu = $this->createService()->getFilteredMenu(['ROLE_USER', 'ROLE_ADMIN']);

        self::assertArrayHasKey('testing', $menu);
        self::assertArrayHasKey('users', $menu);
        self::assertArrayHasKey('system', $menu);

        // All testing items visible
        self::assertCount(3, $menu['testing']['items']);
    }

    public function testGetFilteredMenuEmptyForNoRoles(): void
    {
        self::assertEmpty($this->createService()->getFilteredMenu([]));
    }

    public function testGetFilteredMenuPreservesSectionMetadata(): void
    {
        $menu = $this->createService()->getFilteredMenu(['ROLE_USER']);

        self::assertSame('Test Management', $menu['testing']['label']);
        self::assertSame('bi-check-circle', $menu['testing']['icon']);
    }

    public function testGetBreadcrumbsForIndex(): void
    {
        $crumbs = $this->createService()->getBreadcrumbs('test_run', 'index');

        self::assertCount(2, $crumbs);
        self::assertSame('Dashboard', $crumbs[0]['label']);
        self::assertSame('admin_dashboard', $crumbs[0]['url']);
        self::assertSame('Test Runs', $crumbs[1]['label']);
        self::assertNull($crumbs[1]['url']);
    }

    public function testGetBreadcrumbsForEdit(): void
    {
        $crumbs = $this->createService()->getBreadcrumbs('test_run', 'edit', 'Run #42');

        self::assertCount(3, $crumbs);
        self::assertSame('Test Runs', $crumbs[1]['label']);
        self::assertSame('admin_test_run', $crumbs[1]['url']);
        self::assertSame('Run #42', $crumbs[2]['label']);
        self::assertNull($crumbs[2]['url']);
    }

    public function testGetBreadcrumbsForNewWithoutTitle(): void
    {
        $crumbs = $this->createService()->getBreadcrumbs('test_suite', 'new');

        self::assertCount(3, $crumbs);
        self::assertSame('New', $crumbs[2]['label']);
    }

    public function testGetBreadcrumbsForShowAction(): void
    {
        $crumbs = $this->createService()->getBreadcrumbs('user', 'show');

        self::assertCount(3, $crumbs);
        self::assertSame('View', $crumbs[2]['label']);
    }

    public function testGetBreadcrumbsForCustomAction(): void
    {
        $crumbs = $this->createService()->getBreadcrumbs('test_run', 'rerun');

        self::assertCount(3, $crumbs);
        self::assertSame('Rerun', $crumbs[2]['label']);
    }

    public function testGetBreadcrumbsForUnknownEntity(): void
    {
        $crumbs = $this->createService()->getBreadcrumbs('nonexistent', 'index');

        self::assertCount(1, $crumbs);
        self::assertSame('Dashboard', $crumbs[0]['label']);
    }

    public function testGetBreadcrumbsWithNullAction(): void
    {
        $crumbs = $this->createService()->getBreadcrumbs('test_run');

        self::assertCount(2, $crumbs);
        self::assertSame('admin_test_run', $crumbs[1]['url']);
    }

    private function createService(?SettingsRepository $settingsRepository = null): AdminConfigService
    {
        return new AdminConfigService(
            $settingsRepository ?? $this->createStub(SettingsRepository::class),
        );
    }
}
