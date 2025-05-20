<?php

namespace Crm\GiftsModule\Seeders;

use Crm\ApplicationModule\Seeders\ISeeder;
use Crm\UsersModule\Repositories\AddressTypesRepository;
use Nette\Localization\Translator;
use Symfony\Component\Console\Output\OutputInterface;

class AddressTypesSeeder implements ISeeder
{
    const GIFT_SUBSCRIPTION_ADDRESS_TYPE = 'gift_subscription';

    private $addressTypesRepository;

    private $translator;

    public function __construct(
        AddressTypesRepository $addressTypesRepository,
        Translator $translator,
    ) {
        $this->addressTypesRepository = $addressTypesRepository;
        $this->translator = $translator;
    }

    public function seed(OutputInterface $output)
    {
        $types = [
            self::GIFT_SUBSCRIPTION_ADDRESS_TYPE =>
                $this->translator->translate('gifts.seeders.address_types.gift_subscription_type'),
        ];

        foreach ($types as $type => $title) {
            if ($this->addressTypesRepository->findBy('type', $type)) {
                $output->writeln("  * address type <info>{$type}</info> exists");
            } else {
                $this->addressTypesRepository->insert([
                    'type' => $type,
                    'title' => $title,
                ]);
                $output->writeln("  <comment>* address type <info>{$type}</info> created</comment>");
            }
        }
    }
}
