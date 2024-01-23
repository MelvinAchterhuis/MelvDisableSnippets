<?php declare(strict_types=1);

namespace Melv\DisableSnippets\Extension;

use Shopware\Core\System\SystemConfig\SystemConfigService;
use Symfony\Bridge\Twig\TokenParser\TransDefaultDomainTokenParser;
use Symfony\Bridge\Twig\TokenParser\TransTokenParser;
use Symfony\Component\Translation\TranslatableMessage;
use Symfony\Contracts\Translation\TranslatableInterface;
use Symfony\Contracts\Translation\TranslatorInterface;
use Symfony\Contracts\Translation\TranslatorTrait;
use Twig\Extension\AbstractExtension;
use Twig\TwigFilter;
use Twig\TwigFunction;

// Help opcache.preload discover always-needed symbols
class_exists(TranslatorInterface::class);
class_exists(TranslatorTrait::class);

final class TranslationExtension extends AbstractExtension
{
    public function __construct(
        private readonly SystemConfigService $systemConfigService,
        private ?TranslatorInterface $translator = null,
    ) {
    }

    public function getTranslator(): TranslatorInterface
    {
        if (null === $this->translator) {
            if (!interface_exists(TranslatorInterface::class)) {
                throw new \LogicException(sprintf('You cannot use the "%s" if the Translation Contracts are not available. Try running "composer require symfony/translation".', __CLASS__));
            }

            $this->translator = new class() implements TranslatorInterface {
                use TranslatorTrait;
            };
        }

        return $this->translator;
    }

    public function getFunctions(): array
    {
        return [
            new TwigFunction('t', $this->createTranslatable(...)),
        ];
    }

    public function getFilters(): array
    {
        return [
            new TwigFilter('trans', $this->trans(...)),
        ];
    }

    public function getTokenParsers(): array
    {
        return [
            // {% trans %}Symfony is great!{% endtrans %}
            new TransTokenParser(),

            // {% trans_default_domain "foobar" %}
            new TransDefaultDomainTokenParser(),
        ];
    }

    /**
     * @param array|string $arguments Can be the locale as a string when $message is a TranslatableInterface
     */
    public function trans(string|\Stringable|TranslatableInterface|null $message, array|string $arguments = [], string $domain = null, string $locale = null, int $count = null): string
    {
        if($this->systemConfigService->get('MelvDisableSnippets.config.disableSnippets')) {
            return $message;
        }

        if ($message instanceof TranslatableInterface) {
            if ([] !== $arguments && !\is_string($arguments)) {
                throw new \TypeError(sprintf('Argument 2 passed to "%s()" must be a locale passed as a string when the message is a "%s", "%s" given.', __METHOD__, TranslatableInterface::class, get_debug_type($arguments)));
            }

            if ($message instanceof TranslatableMessage && '' === $message->getMessage()) {
                return '';
            }

            return $message->trans($this->getTranslator(), $locale ?? (\is_string($arguments) ? $arguments : null));
        }

        if (!\is_array($arguments)) {
            throw new \TypeError(sprintf('Unless the message is a "%s", argument 2 passed to "%s()" must be an array of parameters, "%s" given.', TranslatableInterface::class, __METHOD__, get_debug_type($arguments)));
        }

        if ('' === $message = (string) $message) {
            return '';
        }

        if (null !== $count) {
            $arguments['%count%'] = $count;
        }

        return $this->getTranslator()->trans($message, $arguments, $domain, $locale);
    }

    public function createTranslatable(string $message, array $parameters = [], string $domain = null): TranslatableMessage
    {
        if (!class_exists(TranslatableMessage::class)) {
            throw new \LogicException(sprintf('You cannot use the "%s" as the Translation Component is not installed. Try running "composer require symfony/translation".', __CLASS__));
        }

        return new TranslatableMessage($message, $parameters, $domain);
    }
}
