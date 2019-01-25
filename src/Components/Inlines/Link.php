<?php

namespace Erusev\Parsedown\Components\Inlines;

use Erusev\Parsedown\AST\Handler;
use Erusev\Parsedown\AST\StateRenderable;
use Erusev\Parsedown\Components\Inline;
use Erusev\Parsedown\Configurables\DefinitionBook;
use Erusev\Parsedown\Configurables\InlineTypes;
use Erusev\Parsedown\Configurables\SafeMode;
use Erusev\Parsedown\Html\Renderables\Element;
use Erusev\Parsedown\Html\Renderables\Text;
use Erusev\Parsedown\Parsedown;
use Erusev\Parsedown\Parsing\Excerpt;
use Erusev\Parsedown\State;

/** @psalm-type _Metadata=array{href: string, title?: string} */
final class Link implements Inline
{
    use WidthTrait, DefaultBeginPosition;

    /** @var string */
    private $label;

    /** @var string */
    private $url;

    /** @var string|null */
    private $title;

    /**
     * @param string $label
     * @param string $url
     * @param string|null $title
     * @param int $width
     */
    public function __construct($label, $url, $title, $width)
    {
        $this->label = $label;
        $this->url = $url;
        $this->title = $title;
        $this->width = $width;
    }

    /**
     * @param Excerpt $Excerpt
     * @param State $State
     * @return static|null
     */
    public static function build(Excerpt $Excerpt, State $State)
    {
        $remainder = $Excerpt->text();

        if (! \preg_match('/\[((?:[^][]++|(?R))*+)\]/', $remainder, $matches)) {
            return null;
        }

        $label = $matches[1];

        $width = \strlen($matches[0]);

        $remainder = \substr($remainder, $width);

        if (\preg_match('/^[(]\s*+((?:[^ ()]++|[(][^ )]+[)])++)(?:[ ]+("[^"]*+"|\'[^\']*+\'))?\s*+[)]/', $remainder, $matches)) {
            $url = $matches[1];
            $title = isset($matches[2]) ? \substr($matches[2], 1, - 1) : null;

            $width += \strlen($matches[0]);

            return new self($label, $url, $title, $width);
        } else {
            if (\preg_match('/^\s*\[(.*?)\]/', $remainder, $matches)) {
                $definition = \strlen($matches[1]) ? $matches[1] : $label;
                $definition = \strtolower($definition);

                $width += \strlen($matches[0]);
            } else {
                $definition = \strtolower($label);
            }

            $data = $State->get(DefinitionBook::class)->lookup($definition);

            if (! isset($data)) {
                return null;
            }

            $url = $data['url'];
            $title = isset($data['title']) ? $data['title'] : null;

            return new self($label, $url, $title, $width);
        }
    }

    /** @return string */
    public function label()
    {
        return $this->label;
    }

    /** @return string */
    public function url()
    {
        return $this->url;
    }

    /** @return string|null */
    public function title()
    {
        return $this->title;
    }

    /**
     * @return Handler<Element|Text>
     */
    public function stateRenderable()
    {
        return new Handler(
            /** @return Element|Text */
            function (State $State) {
                $attributes = ['href' => $this->url];

                if (isset($this->title)) {
                    $attributes['title'] = $this->title;
                }

                if ($State->get(SafeMode::class)->isEnabled()) {
                    $attributes['href'] = Element::filterUnsafeUrl($attributes['href']);
                }

                $NewState = $State->setting(
                    $State->get(InlineTypes::class)->removing([Url::class])
                );

                return new Element(
                    'a',
                    $attributes,
                    $State->applyTo((new Parsedown($NewState))->line($this->label))
                );
            }
        );
    }

    /**
     * @return Text
     */
    public function bestPlaintext()
    {
        return new Text($this->label);
    }
}
