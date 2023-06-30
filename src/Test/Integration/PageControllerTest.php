<?php declare(strict_types=1);

namespace SwagShopwarePwa\Test\Integration;

use PHPUnit\Framework\TestCase;
use Shopware\Core\Content\Cms\DataResolver\FieldConfig;
use Shopware\Core\Content\Product\Aggregate\ProductVisibility\ProductVisibilityDefinition;
use Shopware\Core\Defaults;
use Shopware\Core\Framework\Context;
use Shopware\Core\Framework\DataAbstractionLayer\EntityRepository;
use Shopware\Core\Framework\Test\TestCaseBase\IntegrationTestBehaviour;
use Shopware\Core\Framework\Test\TestCaseBase\SalesChannelApiTestBehaviour;
use Shopware\Core\Framework\Test\TestDataCollection;
use Shopware\Core\Framework\Uuid\Uuid;
use Symfony\Bundle\FrameworkBundle\KernelBrowser;
use Symfony\Component\HttpFoundation\Response;

class PageControllerTest extends TestCase
{
    use IntegrationTestBehaviour;
    use SalesChannelApiTestBehaviour;

    const ENDPOINT_PAGE = '/store-api/pwa/page';

    private KernelBrowser $browser;

    private TestDataCollection $ids;

    private EntityRepository $seoUrlRepository;

    private EntityRepository $categoryRepository;

    private EntityRepository $cmsPageRepository;

    private EntityRepository $salesChannelDomainRepository;

    private EntityRepository $salesChannelRepository;

    private EntityRepository $landingPageRepository;

    public function setUp(): void
    {
        $this->ids = new TestDataCollection();

        $this->browser = $this->createCustomSalesChannelBrowser([
            'id' => $this->ids->create('salesChannelId'),
        ]);

        $this->ids->get('salesChannelId');

        $this->ids->create('categoryId');
        $this->ids->create('cmsPageId');
        $this->ids->create('cmsProductPageId');

        $this->ids->create('productActiveId');
        $this->ids->create('productActiveWithMainCategoriesId');
        $this->ids->create('productInActiveId');

        $this->ids->create('childCategoryId');
        $this->ids->create('child2CategoryId');
        $this->ids->create('child3CategoryId');
    }

    /**
     * @group pwa-page-category
     */
    public function testResolveCategoryPageRootPath(): void
    {
        $this->createCmsPage();
        $this->createCategories();

        $content = [
            'path' => ''
        ];

        $this->browser->request(
            'POST',
            self::ENDPOINT_PAGE,
            $content
        );

        $response = json_decode($this->browser->getResponse()->getContent());

        static::assertObjectHasAttribute('cmsPage', $response);

        static::assertEquals('frontend.navigation.page', $response->resourceType);
        static::assertObjectHasAttribute('resourceIdentifier', $response);
        static::assertEquals($this->ids->get('categoryId'), $response->resourceIdentifier);
    }

    /**
     * @group pwa-page-category
     */
    public function testResolveCategoryPage(): void
    {
        $this->createCmsPage();
        $this->createCategories();
        $this->createSeoUrls();

        $content = [
            'path' => 'Home-Shoes/'
        ];

        $this->browser->request(
            'POST',
            self::ENDPOINT_PAGE,
            $content
        );

        $response = json_decode($this->browser->getResponse()->getContent());


        static::assertObjectHasAttribute('cmsPage', $response);
        static::assertObjectHasAttribute('breadcrumb', $response);

        static::assertObjectHasAttribute('category', $response);
        static::assertNotNull($response->category->media);

        static::assertEquals('frontend.navigation.page', $response->resourceType);
        static::assertObjectHasAttribute('resourceIdentifier', $response);
        static::assertNotNull($response->resourceIdentifier);
    }

    /**
     * @group pwa-page-category
     */
    public function testResolveCategoryBreadcrumbLink(): void
    {
        $this->createCmsPage();
        $this->createCategories();
        $this->createSeoUrls();

        $content = [
            'path' => 'Home-Shoes/Children-level-2/'
        ];

        $this->browser->request(
            'POST',
            self::ENDPOINT_PAGE,
            $content
        );

        $response = json_decode($this->browser->getResponse()->getContent(), true);

        static::assertArrayHasKey('breadcrumb', $response);

        static::assertEquals('/Home-Shoes/Children-canonical/', $response['breadcrumb'][$this->ids->get('childCategoryId')]['path']);
        static::assertEquals('/Home-Shoes/Children-level-2/', $response['breadcrumb'][$this->ids->get('child2CategoryId')]['path']);
    }

    /**
     * @group pwa-page-category
     */
    public function testResolveCategoryPageTechnicalUrl(): void
    {
        $this->createCmsPage();
        $this->createCategories();

        $content = [
            'path' => '/navigation/' . $this->ids->get('categoryId')
        ];

        $this->browser->request(
            'POST',
            self::ENDPOINT_PAGE,
            $content
        );

        $response = json_decode($this->browser->getResponse()->getContent());

        static::assertObjectHasAttribute('cmsPage', $response);
        static::assertObjectHasAttribute('breadcrumb', $response);

        static::assertEquals('frontend.navigation.page', $response->resourceType);
        static::assertObjectHasAttribute('resourceIdentifier', $response);
        static::assertNotNull($response->resourceIdentifier);
    }

    /**
     * @group pwa-page-category
     */
    public function testResolveCategoryWithoutCmsPage(): void
    {
        $this->createCategories(false);
        $this->createSeoUrls();

        $content = [
            'path' => 'Home-Shoes/'
        ];

        $this->browser->request(
            'POST',
            self::ENDPOINT_PAGE,
            $content
        );

        $response = json_decode($this->browser->getResponse()->getContent());

        static::assertObjectHasAttribute('cmsPage', $response);
        static::assertNull($response->cmsPage);

        static::assertEquals('frontend.navigation.page', $response->resourceType);
        static::assertObjectHasAttribute('resourceIdentifier', $response);
        static::assertNotNull($response->resourceIdentifier);
    }

    /**
     * @group pwa-page-category
     */
    public function testResolveCategoryPageWithIncludes(): void
    {
        $this->createCmsPage();
        $this->createCategories();
        $this->createSeoUrls();

        $content = [
            'path' => 'Home-Shoes/',
            'includes' => [
                'pwa_page_result' => ['cmsPage'],
                'section' => ['id']
            ]
        ];

        $this->browser->request(
            'POST',
            self::ENDPOINT_PAGE,
            $content
        );

        $response = json_decode($this->browser->getResponse()->getContent());

        static::assertObjectHasAttribute('cmsPage', $response);
        static::assertNotNull($response->cmsPage);
        static::assertObjectHasAttribute('sections', $response->cmsPage);
        static::assertObjectNotHasAttribute('blocks', $response->cmsPage);
        static::assertObjectNotHasAttribute('breadcrumb', $response);
    }

    /**
     * @group pwa-page-landing
     */
    public function testResolveLandingPage(): void
    {
        $this->createCmsPage();
        $this->createLandingPage(true);
        $this->createSeoUrls();

        $content = [
            'path' => 'my-landing-page/exists'
        ];

        $this->browser->request(
            'POST',
            self::ENDPOINT_PAGE,
            $content
        );

        $response = json_decode($this->browser->getResponse()->getContent());


        static::assertObjectHasAttribute('cmsPage', $response);

        static::assertEquals('shopware AG', $response->cmsPage->name);
        static::assertEquals('frontend.landing.page', $response->resourceType);
        static::assertObjectHasAttribute('resourceIdentifier', $response);
        static::assertNotNull($response->resourceIdentifier);
    }

    /**
     * @group pwa-page-landing
     */
    public function testResolveLandingPageTechnicalUrl(): void
    {
        $this->createCmsPage();
        $this->createLandingPage(true);
        $this->createSeoUrls();

        $content = [
            'path' => 'landingPage/' . $this->ids->get('landingPageId')
        ];

        $this->browser->request(
            'POST',
            self::ENDPOINT_PAGE,
            $content
        );

        $response = json_decode($this->browser->getResponse()->getContent());


        static::assertObjectHasAttribute('cmsPage', $response);

        static::assertStringContainsString('my-landing-page', $response->canonicalPathInfo);
        static::assertEquals('frontend.landing.page', $response->resourceType);
        static::assertObjectHasAttribute('resourceIdentifier', $response);
        static::assertNotNull($response->resourceIdentifier);
    }

    /**
     * @group pwa-page-landing
     */
    public function testResolveLandingPageWihoutCmsPage(): void
    {
        $this->createLandingPage(false);
        $this->createSeoUrls();

        $content = [
            'path' => 'my-landing-page/exists'
        ];

        $this->browser->request(
            'POST',
            self::ENDPOINT_PAGE,
            $content
        );

        $response = json_decode($this->browser->getResponse()->getContent());


        static::assertObjectHasAttribute('cmsPage', $response);

        static::assertNull($response->cmsPage);

        static::assertEquals('frontend.landing.page', $response->resourceType);
        static::assertObjectHasAttribute('resourceIdentifier', $response);
        static::assertNotNull($response->resourceIdentifier);
    }

    /**
     * @group pwa-page-product
     */
    public function testResolveProductPage(): void
    {
        $this->createCategories(false);
        $this->createProduct();
        $this->createSalesChannelDomain();
        $this->createSeoUrls();

        $content = [
            'path' => '/foo-bar/prod'
        ];

        $this->browser->request(
            'POST',
            self::ENDPOINT_PAGE,
            $content
        );

        $response = json_decode($this->browser->getResponse()->getContent());

        static::assertObjectHasAttribute('product', $response);

        static::assertEquals('frontend.detail.page', $response->resourceType);
        static::assertObjectHasAttribute('resourceIdentifier', $response);
        static::assertNotNull($response->resourceIdentifier);
    }

    /**
     * @group pwa-page-product
     */
    public function testResolveProductPageWithAssociation(): void
    {
        $this->createCategories(false);
        $this->createProduct();
        $this->createSalesChannelDomain();

        $content = [
            'path' => '/detail/' . $this->ids->get('productActiveId'),
            'associations' => [
                'manufacturer' => [],
                'categories' => []
            ]
        ];

        $this->browser->request(
            'POST',
            self::ENDPOINT_PAGE,
            $content
        );

        $response = json_decode($this->browser->getResponse()->getContent(), true);

        static::assertArrayHasKey('product', $response);
        static::assertArrayHasKey('manufacturer', $response['product']);
        static::assertNotNull($response['product']['manufacturer']);
        static::assertArrayHasKey('categories', $response['product']);
        static::assertNotNull($response['product']['categories']);
    }

    /**
     * @group pwa-page-product
     */
    public function testResolveProductPageTechnicalUrl(): void
    {
        $this->createCategories(false);
        $this->createProduct();
        $this->createSalesChannelDomain();

        $content = [
            'path' => '/detail/' . $this->ids->get('productActiveId')
        ];

        $this->browser->request(
            'POST',
            self::ENDPOINT_PAGE,
            $content
        );

        $response = json_decode($this->browser->getResponse()->getContent());

        static::assertObjectHasAttribute('product', $response);

        static::assertEquals('frontend.detail.page', $response->resourceType);
        static::assertObjectHasAttribute('resourceIdentifier', $response);
        static::assertNotNull($response->resourceIdentifier);
    }

    /**
     * @group pwa-page-product
     */
    public function testResolveProductPageForInactive(): void
    {
        $this->createCategories(false);
        $this->createProduct();
        $this->createSalesChannelDomain();
        $this->createSeoUrls();

        $content = [
            'path' => '/foo-bar/prod-inactive'
        ];

        $this->browser->request(
            'POST',
            self::ENDPOINT_PAGE,
            $content
        );

        $response = json_decode($this->browser->getResponse()->getContent());

        static::assertObjectHasAttribute('errors', $response);
        static::assertIsArray($response->errors);

        static::assertEquals(404, $response->errors[0]->status);
        static::assertEquals('CONTENT__PRODUCT_NOT_FOUND', $response->errors[0]->code);

    }

    /**
     * @group pwa-page-product
     */
    public function testResolveProductPageWithCmsPage(): void
    {
        $this->createCmsPage();
        $this->createCategories();
        $this->createProduct(true);
        $this->createSeoUrls();

        $content = [
            'path' => '/detail/' . $this->ids->get('productActiveId'),
            'includes' => [
                'pwa_page_result' => ['cmsPage']
            ]
        ];

        $this->browser->request(
            'POST',
            self::ENDPOINT_PAGE,
            $content
        );

        $response = json_decode($this->browser->getResponse()->getContent());

        static::assertObjectHasAttribute('cmsPage', $response);
        static::assertNotNull($response->cmsPage);
    }

    /**
     * @group pwa-page-product
     */
    public function testResolveProductHasBreadcrumbsLinks(): void
    {
        $this->createCategories(false);
        $this->createProduct();
        $this->createSalesChannelDomain();
        $this->createSeoUrls();

        $content = [
            'path' => '/foo-bar/prod-has-breadcrumb'
        ];

        $this->browser->request(
            'POST',
            self::ENDPOINT_PAGE,
            $content
        );

        $response = json_decode($this->browser->getResponse()->getContent(), true);

        static::assertArrayHasKey('breadcrumb', $response);

        static::assertEquals('/Home-Shoes/Children-canonical/', $response['breadcrumb'][$this->ids->get('childCategoryId')]['path']);
        static::assertEquals('/Home-Shoes/Children-level-2/', $response['breadcrumb'][$this->ids->get('child2CategoryId')]['path']);
        static::assertEquals('/navigation/' . $this->ids->get('child3CategoryId'), $response['breadcrumb'][$this->ids->get('child3CategoryId')]['path']);
    }

    /**
     * @group pwa-page-product
     */
    public function testResolveProductHasNoBreadcrumbsLinks(): void
    {
        $this->createCategories(false);
        $this->createProduct();
        $this->createSalesChannelDomain();
        $this->createSeoUrls();

        $content = [
            'path' => '/detail/' . $this->ids->get('productActiveId')
        ];

        $this->browser->request(
            'POST',
            self::ENDPOINT_PAGE,
            $content
        );

        $response = json_decode($this->browser->getResponse()->getContent(), true);

        static::assertArrayHasKey('breadcrumb', $response);
        static::assertNull($response['breadcrumb']);
    }

    /**
     * @group pwa-page-routing
     */
    public function testResolveCanonicalUrl(): void
    {
        $this->createCmsPage();
        $this->createCategories();
        $this->createSeoUrls();

        $content = [
            'path' => 'Home-Shoes/',
            'includes' => [
                'pwa_page_result' => ['canonicalPathInfo']
            ]
        ];

        $this->browser->request(
            'POST',
            self::ENDPOINT_PAGE,
            $content
        );

        $response = json_decode($this->browser->getResponse()->getContent());

        static::assertObjectHasAttribute('canonicalPathInfo', $response);
        static::assertEquals('/Home-Shoes/canonical/', $response->canonicalPathInfo);
    }

    /**
     * @group pwa-page-routing
     */
    public function testResolveInvalidUrl(): void
    {
        $this->browser->request(
            'POST',
            self::ENDPOINT_PAGE
        );

        $response = json_decode($this->browser->getResponse()->getContent());

        static::assertEquals(\Symfony\Component\HttpFoundation\Response::HTTP_NOT_FOUND, $this->browser->getResponse()->getStatusCode());
        static::assertObjectHasAttribute('errors', $response);
    }

    private function createSalesChannelDomain()
    {
        $salesChannelDomainRepository = $this->getContainer()->get('sales_channel_domain.repository');
        $salesChannelDomainRepository->create([
            [
                'url' => '/',
                'salesChannelId' => $this->ids->get('salesChannelId'),
                'languageId' => Defaults::LANGUAGE_SYSTEM,
                'currencyId' => Defaults::CURRENCY,
                'snippetSetId' => $this->getSnippetSetIdForLocale('en-GB')
            ]
        ], Context::createDefaultContext());
    }

    private function createProduct(bool $withCmsPage = false)
    {
        $categoryId = Uuid::randomHex();
        $data = [
            [
                'id' => $this->ids->get('productActiveId'),
                'productNumber' => Uuid::randomHex(),
                'stock' => 10,
                'active' => true,
                'name' => 'test',
                'price' => [['currencyId' => Defaults::CURRENCY, 'gross' => 99, 'net' => 99, 'linked' => false]],
                'manufacturer' => ['name' => 'test'],
                'tax' => ['name' => 'test', 'taxRate' => 99],
                'categories' => [
                    ['id' => $categoryId, 'name' => 'sampleCategory'],
                ],
                'visibilities' => [
                    [
                        'salesChannelId' => $this->ids->get('salesChannelId'),
                        'visibility' => ProductVisibilityDefinition::VISIBILITY_ALL,
                    ],
                ],
                'cmsPageId' => $withCmsPage ? $this->ids->get('cmsProductPageId') : null
            ],
            [
                'id' => $this->ids->get('productActiveWithMainCategoriesId'),
                'productNumber' => Uuid::randomHex(),
                'stock' => 10,
                'active' => true,
                'name' => 'test',
                'price' => [['currencyId' => Defaults::CURRENCY, 'gross' => 99, 'net' => 99, 'linked' => false]],
                'manufacturer' => ['name' => 'test'],
                'tax' => ['name' => 'test', 'taxRate' => 99],
                'categories' => [
                    ['id' => $categoryId, 'name' => 'sampleCategory'],
                ],
                'mainCategories' => [[
                    'categoryId' => $this->ids->get('child3CategoryId'),
                    'id' => Uuid::randomHex(),
                    'salesChannelId' => $this->ids->get('salesChannelId'),
                ]],
                'visibilities' => [
                    [
                        'salesChannelId' => $this->ids->get('salesChannelId'),
                        'visibility' => ProductVisibilityDefinition::VISIBILITY_ALL,
                    ],
                ],
            ],
            [
                'id' => $this->ids->get('productInactiveId'),
                'productNumber' => Uuid::randomHex(),
                'stock' => 10,
                'active' => false,
                'name' => 'test',
                'price' => [['currencyId' => Defaults::CURRENCY, 'gross' => 99, 'net' => 99, 'linked' => false]],
                'manufacturer' => ['name' => 'test'],
                'tax' => ['name' => 'test', 'taxRate' => 99],
                'categories' => [
                    ['id' => $categoryId, 'name' => 'sampleCategory'],
                ],
                'visibilities' => [
                    [
                        'salesChannelId' => $this->ids->get('salesChannelId'),
                        'visibility' => ProductVisibilityDefinition::VISIBILITY_ALL,
                    ],
                ]
            ]
        ];

        $this->getContainer()->get('product.repository')->create($data, Context::createDefaultContext());
    }

    private function createSeoUrls()
    {
        $seoUrlRepository = $this->getContainer()->get('seo_url.repository');
        $seoUrlRepository->create([
            [
                'salesChannelId' => $this->ids->get('salesChannelId'),
                'languageId' => Defaults::LANGUAGE_SYSTEM,
                'routeName' => 'frontend.navigation.page',
                'pathInfo' => '/navigation/' . $this->ids->get('categoryId'),
                'seoPathInfo' => 'Home-Shoes/',
                'foreignKey' => $this->ids->get('categoryId'),
                'isValid' => true,
                'isCanonical' => false,
            ],
            [
                'salesChannelId' => $this->ids->get('salesChannelId'),
                'languageId' => Defaults::LANGUAGE_SYSTEM,
                'routeName' => 'frontend.navigation.page',
                'pathInfo' => '/navigation/' . $this->ids->get('categoryId'),
                'seoPathInfo' => 'Home-Shoes/canonical/',
                'foreignKey' => $this->ids->get('categoryId'),
                'isValid' => true,
                'isCanonical' => true,
            ],
            [
                'salesChannelId' => $this->ids->get('salesChannelId'),
                'languageId' => Defaults::LANGUAGE_SYSTEM,
                'routeName' => 'frontend.navigation.page',
                'pathInfo' => '/navigation/' . $this->ids->get('childCategoryId'),
                'seoPathInfo' => 'Home-Shoes/Children/',
                'foreignKey' => $this->ids->get('childCategoryId'),
                'isValid' => true,
                'isCanonical' => false,
            ],
            [
                'salesChannelId' => $this->ids->get('salesChannelId'),
                'languageId' => Defaults::LANGUAGE_SYSTEM,
                'routeName' => 'frontend.navigation.page',
                'pathInfo' => '/navigation/' . $this->ids->get('childCategoryId'),
                'seoPathInfo' => 'Home-Shoes/Children-canonical/',
                'foreignKey' => $this->ids->get('childCategoryId'),
                'isValid' => true,
                'isCanonical' => true,
            ],
            [
                'salesChannelId' => $this->ids->get('salesChannelId'),
                'languageId' => Defaults::LANGUAGE_SYSTEM,
                'routeName' => 'frontend.navigation.page',
                'pathInfo' => '/navigation/' . $this->ids->get('child2CategoryId'),
                'seoPathInfo' => 'Home-Shoes/Children-level-2/',
                'foreignKey' => $this->ids->get('child2CategoryId'),
                'isValid' => true,
                'isCanonical' => true,
            ],
            [
                'salesChannelId' => $this->ids->get('salesChannelId'),
                'languageId' => Defaults::LANGUAGE_SYSTEM,
                'routeName' => 'frontend.detail.page',
                'pathInfo' => '/detail/' . $this->ids->get('productActiveId'),
                'seoPathInfo' => 'foo-bar/prod',
                'foreignKey' => $this->ids->get('productActiveId'),
                'isValid' => true,
                'isCanonical' => false,
            ],
            [
                'salesChannelId' => $this->ids->get('salesChannelId'),
                'languageId' => Defaults::LANGUAGE_SYSTEM,
                'routeName' => 'frontend.detail.page',
                'pathInfo' => '/detail/' . $this->ids->get('productInactiveId'),
                'seoPathInfo' => 'foo-bar/prod-inactive',
                'foreignKey' => $this->ids->get('productInactiveId'),
                'isValid' => true,
                'isCanonical' => false,
            ],
            [
                'salesChannelId' => $this->ids->get('salesChannelId'),
                'languageId' => Defaults::LANGUAGE_SYSTEM,
                'routeName' => 'frontend.detail.page',
                'pathInfo' => '/detail/' . $this->ids->get('productActiveWithMainCategoriesId'),
                'seoPathInfo' => 'foo-bar/prod-has-breadcrumb',
                'foreignKey' => $this->ids->get('productActiveWithMainCategoriesId'),
                'isValid' => true,
                'isCanonical' => false,
            ],
            [
                'salesChannelId' => $this->ids->get('salesChannelId'),
                'languageId' => Defaults::LANGUAGE_SYSTEM,
                'routeName' => 'frontend.landing.page',
                'pathInfo' => '/landingPage/' . $this->ids->get('landingPageId'),
                'seoPathInfo' => 'my-landing-page/exists',
                'foreignKey' => $this->ids->get('landingPageId'),
                'isValid' => true,
                'isCanonical' => false,
            ],
        ], Context::createDefaultContext());
    }

    private function createCategories(bool $withCmsPage = true)
    {
        $categoryRepository = $this->getContainer()->get('category.repository');
        $categoryRepository->create([
            [
                'id' => $this->ids->get('categoryId'),
                'salesChannelId' => $this->ids->get('salesChannelId'),
                'name' => 'My test category',
                'cmsPageId' => $withCmsPage ? $this->ids->get('cmsPageId') : null,
                'media' => [
                    'id' => $this->ids->get('categoryMediaId')
                ],
                'children' => [
                    [
                        'id' => $this->ids->get('childCategoryId'),
                        'name' => 'Child category level 1',
                        'children' => [
                            [
                                'id' => $this->ids->get('child2CategoryId'),
                                'name' => 'Child category level 2',
                                'children' => [
                                    [
                                        'id' => $this->ids->get('child3CategoryId'),
                                        'name' => 'Child category level 3 (without seoUrl)'
                                    ]
                                ]
                            ]
                        ]
                    ]
                ]
            ]
        ], Context::createDefaultContext());

        $salesChannelRepository = $this->getContainer()->get('sales_channel.repository');
        $salesChannelRepository->upsert([
            [
                'id' => $this->ids->get('salesChannelId'),
                'navigationCategoryId' => $this->ids->get('categoryId')
            ]
        ], Context::createDefaultContext());
    }

    private function createLandingPage(bool $withCmsPage = true) {
        $landingPageRepository = $this->getContainer()->get('landing_page.repository');

        $landingPageRepository->create([
            [
                'id' => $this->ids->get('landingPageId'),
                'salesChannels' => [
                    [
                        'id' => $this->ids->get('salesChannelId')
                    ]
                ],
                'name' => 'My test landing page',
                'cmsPageId' => $withCmsPage ? $this->ids->get('cmsPageId') : null,
                'url' => 'my-landing-page/exists'
            ]
        ], Context::createDefaultContext());
    }

    private function createCmsPage()
    {
        $landingPage = [
            'id' => $this->ids->get('cmsPageId'),
            'name' => 'shopware AG',
            'type' => 'landing_page',
            'sections' => [
                [
                    'id' => Uuid::randomHex(),
                    'type' => 'default',
                    'position' => 0,
                    'blocks' => [
                        [
                            'position' => 1,
                            'type' => 'image-text',
                            'slots' => [
                                ['type' => 'text', 'slot' => 'left', 'config' => ['content' => ['source' => FieldConfig::SOURCE_STATIC, 'value' => 'Lorem ipsum dolor']]],
                                ['type' => 'image', 'slot' => 'right', 'config' => ['url' => ['source' => FieldConfig::SOURCE_STATIC, 'value' => 'http://shopware.com/image.jpg']]],
                            ],
                        ],
                    ],
                ],
            ],
        ];

        $productPage = [
            'id' => $this->ids->get('cmsProductPageId'),
            'name' => 'shopware AG Detail',
            'type' => 'product_detail',
            'sections' => [
                [
                    'id' => Uuid::randomHex(),
                    'type' => 'default',
                    'position' => 0,
                    'blocks' => [
                        [
                            'position' => 1,
                            'type' => 'product-heading',
                            'slots' => [
                                ['type' => 'product-name', 'slot' => 'left', 'config' => ['content' => ['source' => FieldConfig::SOURCE_STATIC, 'value' => 'Lorem ipsum dolor']]]
                            ],
                        ],
                    ],
                ],
            ],
        ];

        $cmsPageRepository = $this->getContainer()->get('cms_page.repository');
        $cmsPageRepository->upsert([$landingPage, $productPage], Context::createDefaultContext());
    }
}
