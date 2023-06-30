<?php declare(strict_types=1);

namespace SwagShopwarePwa\Pwa\Bundle\Command;

use Shopware\Core\Framework\Adapter\Console\ShopwareStyle;
use SwagShopwarePwa\Pwa\Bundle\AssetService;
use Symfony\Component\Console\Command\Command;
use Symfony\Component\Console\Input\InputInterface;
use Symfony\Component\Console\Output\OutputInterface;

class DumpPluginConfigurationCommand extends Command
{
    protected static $defaultName = 'pwa:dump-plugins';

    public function __construct(
        private AssetService $assetService
    ) {
        parent::__construct();
    }

    protected function configure(): void
    {
        $this->setDescription('Dump PWA plugin configurations and assets');
    }

    protected function execute(InputInterface $input, OutputInterface $output): int
    {
        $io = new ShopwareStyle($input, $output);
        $assetArtifact = $this->assetService->dumpBundles();

        $io->title('Shopware PWA Extension');
        $io->text('Assets');
        $io->comment("Wrote assets to '{$assetArtifact}'");

        return self::SUCCESS;
    }
}
