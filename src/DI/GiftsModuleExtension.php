<?php

namespace Crm\GiftsModule\DI;

use Contributte\Translation\DI\TranslationProviderInterface;
use Crm\GiftsModule\Scenarios\SendNotificationEmailToDonorGenericEvent;
use Nette\Application\IPresenterFactory;
use Nette\DI\CompilerExtension;
use Nette\DI\Definitions\ServiceDefinition;

class GiftsModuleExtension extends CompilerExtension implements TranslationProviderInterface
{
    public function loadConfiguration()
    {
        // load services from config and register them to Nette\DI Container
        $this->compiler->loadDefinitionsFromConfig(
            $this->loadFromFile(__DIR__.'/../config/config.neon')['services']
        );
    }

    public function beforeCompile()
    {
        $builder = $this->getContainerBuilder();
        // load presenters from extension to Nette
        $builder->getDefinition($builder->getByType(IPresenterFactory::class))
            ->addSetup('setMapping', [['Gifts' => 'Crm\GiftsModule\Presenters\*Presenter']]);

        // If crm/remp-mailer-module is enabled and MailTemplatesRepository is available, add generic event for scenarios.
        //
        // This should ideally be independent of REMP Mailer and scenario module should be responsible for providing
        // list of available mail templates from the service it supports.
        $hasMailTemplatesRepository = $builder->getByType(\Crm\RempMailerModule\Repositories\MailTemplatesRepository::class) !== null;
        if ($hasMailTemplatesRepository) {
            // register generic handler to DI
            $definition = new ServiceDefinition();
            $definition->setFactory(SendNotificationEmailToDonorGenericEvent::class);
            $definition->addSetup('addAllowedMailTypeCodes', ['system', 'system_optional']);
            $builder->addDefinition(
                name: 'sendGiftDonorNotificationEmailGenericEvent',
                definition: $definition,
            );

            // configure scenarios generic event
            /** @var ServiceDefinition $genericEventsManager */
            $genericEventsManager = $builder->getDefinition('scenariosGenericEventsManager');
            $genericEventsManager->addSetup('register', ['send_notification_email_to_gift_donor', $definition]);
        }
    }

    /**
     * Return array of directories, that contain resources for translator.
     * @return string[]
     */
    public function getTranslationResources(): array
    {
        return [__DIR__ . '/../lang/'];
    }
}
