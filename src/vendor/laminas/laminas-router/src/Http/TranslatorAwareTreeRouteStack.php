<?php

declare(strict_types=1);

namespace Laminas\Router\Http;

use Laminas\I18n\Translator\TranslatorAwareInterface;
use Laminas\I18n\Translator\TranslatorInterface as Translator;
use Laminas\Router\Exception;
use Laminas\Stdlib\RequestInterface;
use Override;

/**
 * Translator aware tree route stack.
 *
 * @template TRoute of HttpRouteInterface
 * @template-extends TreeRouteStack<TRoute>
 * @final
 */
class TranslatorAwareTreeRouteStack extends TreeRouteStack implements TranslatorAwareInterface
{
    /**
     * Translator used for translatable segments.
     *
     * @var Translator
     */
    protected $translator;

    /**
     * Whether the translator is enabled.
     *
     * @var bool
     */
    protected $translatorEnabled = true;

    /**
     * Translator text domain to use.
     *
     * @var string
     */
    protected $translatorTextDomain = 'default';

    /**
     * @inheritDoc
     * @param int|null $pathOffset
     */
    #[Override]
    public function match(RequestInterface $request, $pathOffset = null, array $options = [])
    {
        if ($this->hasTranslator() && $this->isTranslatorEnabled() && ! isset($options['translator'])) {
            $options['translator'] = $this->getTranslator();
        }

        if (! isset($options['text_domain'])) {
            $options['text_domain'] = $this->getTranslatorTextDomain();
        }

        return parent::match($request, $pathOffset, $options);
    }

    /**
     * @inheritDoc
     * @throws Exception\InvalidArgumentException
     * @throws Exception\RuntimeException
     */
    #[Override]
    public function assemble(array $params = [], array $options = [])
    {
        if ($this->hasTranslator() && $this->isTranslatorEnabled() && ! isset($options['translator'])) {
            $options['translator'] = $this->getTranslator();
        }

        if (! isset($options['text_domain'])) {
            $options['text_domain'] = $this->getTranslatorTextDomain();
        }

        return parent::assemble($params, $options);
    }

    /**
     * setTranslator(): defined by TranslatorAwareInterface.
     *
     * @see    TranslatorAwareInterface::setTranslator()
     *
     * @param  string     $textDomain
     * @return TreeRouteStack
     */
    #[Override]
    public function setTranslator(?Translator $translator = null, $textDomain = null)
    {
        $this->translator = $translator;

        if ($textDomain !== null) {
            $this->setTranslatorTextDomain($textDomain);
        }

        return $this;
    }

    /**
     * getTranslator(): defined by TranslatorAwareInterface.
     *
     * @see    TranslatorAwareInterface::getTranslator()
     *
     * @return Translator
     */
    #[Override]
    public function getTranslator()
    {
        return $this->translator;
    }

    /**
     * hasTranslator(): defined by TranslatorAwareInterface.
     *
     * @see    TranslatorAwareInterface::hasTranslator()
     *
     * @return bool
     */
    #[Override]
    public function hasTranslator()
    {
        return $this->translator !== null;
    }

    /**
     * setTranslatorEnabled(): defined by TranslatorAwareInterface.
     *
     * @see    TranslatorAwareInterface::setTranslatorEnabled()
     *
     * @param  bool $enabled
     * @return TreeRouteStack
     */
    #[Override]
    public function setTranslatorEnabled($enabled = true)
    {
        $this->translatorEnabled = $enabled;
        return $this;
    }

    /**
     * isTranslatorEnabled(): defined by TranslatorAwareInterface.
     *
     * @see    TranslatorAwareInterface::isTranslatorEnabled()
     *
     * @return bool
     */
    #[Override]
    public function isTranslatorEnabled()
    {
        return $this->translatorEnabled;
    }

    /**
     * setTranslatorTextDomain(): defined by TranslatorAwareInterface.
     *
     * @see    TranslatorAwareInterface::setTranslatorTextDomain()
     *
     * @param  string $textDomain
     * @return self
     */
    #[Override]
    public function setTranslatorTextDomain($textDomain = 'default')
    {
        $this->translatorTextDomain = $textDomain;

        return $this;
    }

    /**
     * getTranslatorTextDomain(): defined by TranslatorAwareInterface.
     *
     * @see    TranslatorAwareInterface::getTranslatorTextDomain()
     *
     * @return string
     */
    #[Override]
    public function getTranslatorTextDomain()
    {
        return $this->translatorTextDomain;
    }
}
