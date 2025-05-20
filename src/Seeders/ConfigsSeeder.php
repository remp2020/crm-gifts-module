<?php

namespace Crm\GiftsModule\Seeders;

use Crm\ApplicationModule\Builder\ConfigBuilder;
use Crm\ApplicationModule\Models\Config\ApplicationConfig;
use Crm\ApplicationModule\Repositories\ConfigCategoriesRepository;
use Crm\ApplicationModule\Repositories\ConfigsRepository;
use Crm\ApplicationModule\Seeders\ISeeder;
use Symfony\Component\Console\Output\OutputInterface;

class ConfigsSeeder implements ISeeder
{
    private $configCategoriesRepository;

    private $configsRepository;

    private $configBuilder;

    public function __construct(
        ConfigCategoriesRepository $configCategoriesRepository,
        ConfigsRepository $configsRepository,
        ConfigBuilder $configBuilder,
    ) {
        $this->configCategoriesRepository = $configCategoriesRepository;
        $this->configsRepository = $configsRepository;
        $this->configBuilder = $configBuilder;
    }

    public function seed(OutputInterface $output)
    {
        $categoryName = 'subscriptions.config.category';
        $category = $this->configCategoriesRepository->loadByName($categoryName);
        if (!$category) {
            $output->writeln('  <error>* config category of <info>Subscriptions</info> module is missing</error>');
            return;
        }

        $name = 'gift_subscription_coupon_attachment';
        $config = $this->configsRepository->loadByName($name);
        if (!$config) {
            $this->configBuilder->createNew()
                ->setName($name)
                ->setDisplayName('gifts.config.gift_subscription_coupon_attachment.name')
                ->setDescription('gifts.config.gift_subscription_coupon_attachment.description')
                ->setType(ApplicationConfig::TYPE_STRING)
                ->setAutoload(true)
                ->setConfigCategory($category)
                ->setSorting(500)
                ->save();
            $output->writeln("  <comment>* config item <info>$name</info> created</comment>");
        } else {
            $output->writeln("  * config item <info>$name</info> exists");

            if ($config->category->name != $categoryName) {
                $this->configsRepository->update($config, [
                    'config_category_id' => $category->id,
                ]);
                $output->writeln("  * config item <info>$name</info> updated");
            }
        }
    }
}
