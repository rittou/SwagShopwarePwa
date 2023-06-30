<?php declare(strict_types=1);

namespace SwagShopwarePwa\Pwa\PageLoader;

use Shopware\Core\Content\Category\CategoryEntity;
use Shopware\Core\Content\Category\Exception\CategoryNotFoundException;
use Shopware\Core\Content\Category\SalesChannel\AbstractCategoryRoute;
use Shopware\Core\Content\Cms\CmsPageEntity;
use Shopware\Core\Content\Cms\DataResolver\ResolverContext\EntityResolverContext;
use Shopware\Core\Content\Cms\SalesChannel\SalesChannelCmsPageLoaderInterface;
use Shopware\Core\Framework\DataAbstractionLayer\EntityDefinition;
use Shopware\Core\Framework\DataAbstractionLayer\Search\Criteria;
use SwagShopwarePwa\Pwa\PageLoader\Context\PageLoaderContext;
use SwagShopwarePwa\Pwa\PageLoader\Context\PageLoaderPreviewContext;
use SwagShopwarePwa\Pwa\PageResult\Navigation\NavigationPageResult;
use SwagShopwarePwa\Pwa\PageResult\Navigation\NavigationPageResultHydrator;

/**
 * This is a composite loader which utilizes the Shopware\Core\Content\Cms\SalesChannel\SalesChannelCmsPageLoaderInterface.
 * On top of fetching a resolved and hydrated CMS page, it fetches additional information about the category.
 *
 * @package SwagShopwarePwa\Pwa\PageLoader
 */
class NavigationPageLoader implements PageLoaderInterface
{
    private const RESOURCE_TYPE = 'frontend.navigation.page';

    public function __construct(
        private SalesChannelCmsPageLoaderInterface $cmsPageLoader,
        private NavigationPageResultHydrator $resultHydrator,
        private EntityDefinition $categoryDefinition,
        private AbstractCategoryRoute $categoryRoute
    ) {
    }

    public function getResourceType(): string
    {
        return self::RESOURCE_TYPE;
    }

    /**
     * @param PageLoaderContext $pageLoaderContext
     *
     * @return NavigationPageResult
     *
     * @throws CategoryNotFoundException
     */
    public function load(PageLoaderContext $pageLoaderContext): NavigationPageResult
    {
        $category = $this->categoryRoute->load(
            $pageLoaderContext->getResourceIdentifier(),
            $pageLoaderContext->getRequest(),
            $pageLoaderContext->getContext()
        )->getCategory();

        $cmsPage = $category->getCmsPage();

        if ($pageLoaderContext instanceof PageLoaderPreviewContext) {
            $cmsPage = $this->resolvePreviewCmsPage(
                $pageLoaderContext->getPreviewPageIdentifier(),
                $pageLoaderContext,
                $category
            );
        }

        $pageResult = $this->resultHydrator->hydrate(
            $pageLoaderContext,
            $category,
            $cmsPage
        );

        return $pageResult;
    }

    private function resolvePreviewCmsPage(string $cmsPageId, PageLoaderContext $pageLoaderContext, CategoryEntity $category): ?CmsPageEntity
    {
        if ($cmsPageId) {
            $resolverContext = new EntityResolverContext(
                $pageLoaderContext->getContext(),
                $pageLoaderContext->getRequest(),
                $this->categoryDefinition,
                $category
            );

            $cmsPages = $this->cmsPageLoader->load(
                $pageLoaderContext->getRequest(),
                new Criteria([$cmsPageId]),
                $pageLoaderContext->getContext(),
                $category->getSlotConfig(),
                $resolverContext
            );

            $cmsPage = $cmsPages->get($cmsPageId) ?? null;
        }

        return $cmsPage;
    }
}
