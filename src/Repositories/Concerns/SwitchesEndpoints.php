<?php

declare(strict_types=1);

namespace Typdy\StarterKit\Repositories\Concerns;

/**
 * Enabled the switching between the delivery api and management api endpoints
 * for a repository.
 *
 * @api
 */
trait SwitchesEndpoints
{
    /**
     * By default, repositories make requests to the delivery api. Set this to
     * true if you wish to use the management api by default.
     */
    protected bool $mapi = false;

    /**
     * In management api mode, by default, the latest data is always requested
     * from typdy without checking storage first. Set this to true to match the
     * behavior of delivery api mode by checking storage drivers first. This
     * will increase performance but may return stale data.
     */
    protected bool $mapiFromStorage = false;

    /**
     * In management api mode, by default, data is not persisted to storage
     * unless explicitly saved. Set this to true to match the behavior of the
     * delivery api mode by persisting all data read from typdy. This can result
     * in a lot of unnecessary writes to storage drivers as it's highly likely
     * that you are about to make changes to the data you are reading.
     */
    protected bool $mapiToStorage = false;

    /**
     * @internal
     */
    private ?bool $_originalMapi = null;

    /**
     * @return $this
     */
    public function api(): self
    {
        $this->setOriginalMapi();

        $this->mapi = false;

        return $this;
    }

    /**
     * Switch to using the management api for this request. In magement api
     * mode:
     *  - Constructs are read directly from typdy unless `mapiFromStorage` is
     *    set to true.
     *  - Constructs read from the management api are not persisted to storage
     *    drivers unless explicitly saved or `mapiToStorage` is set to true.
     *  - Constructs can be created, updated, and deleted. And these changes
     *    are persisted to storage drivers.
     *  - Constructs are referenced via their integer id instead of their
     *    identifier string.
     *
     * @return $this
     */
    public function mapi(): self
    {
        $this->setOriginalMapi();

        $this->mapi = true;

        return $this;
    }

    final protected function resetMapi(): void
    {
        if ($this->_originalMapi !== null) {
            $this->mapi = $this->_originalMapi;
        }
    }

    final protected function setOriginalMapi(): void
    {
        if ($this->_originalMapi === null) {
            $this->_originalMapi = $this->mapi;
        }
    }
}
