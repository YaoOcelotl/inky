<?php
/**
 *  Inky.php
 *
 *
 *  @license    see LICENSE File
 *  @filename   Inky.php
 *  @package    inky-parse
 *  @author     Thomas Hampe <github@hampe.co>
 *  @copyright  2013-2016 Thomas Hampe
 *  @date       10.01.16
 */ 


namespace Hampe\Inky;


use Hampe\Inky\Component\CalloutFactory;
use Hampe\Inky\Component\BlockGridFactory;
use Hampe\Inky\Component\ButtonFactory;
use Hampe\Inky\Component\CenterFactory;
use Hampe\Inky\Component\ColumnsFactory;
use Hampe\Inky\Component\ComponentFactoryInterface;
use Hampe\Inky\Component\ContainerFactory;
use Hampe\Inky\Component\InkyFactory;
use Hampe\Inky\Component\MenuFactory;
use Hampe\Inky\Component\MenuItemFactory;
use Hampe\Inky\Component\RowFactory;
use PHPHtmlParser\Dom;
use PHPHtmlParser\Dom\HtmlNode;
use PHPHtmlParser\Exceptions\CircularException;

class Inky
{

    protected $alias = array();

    protected $componentFactory = array();

    /**
     * @var int
     */
    protected $gridColumns;

    public function __construct($gridColumns = 12, $componentFactories = array())
    {
        $this->setGridColumns($gridColumns);
        $this->addComponentFactory(new RowFactory());
        $this->addComponentFactory(new ContainerFactory());
        $this->addComponentFactory(new ButtonFactory());
        $this->addComponentFactory(new InkyFactory());
        $this->addComponentFactory(new BlockGridFactory());
        $this->addComponentFactory(new MenuFactory());
        $this->addComponentFactory(new MenuItemFactory());
        $this->addComponentFactory(new ColumnsFactory());
        $this->addComponentFactory(new CalloutFactory());
        $this->addComponentFactory(new CenterFactory());

        foreach($componentFactories as $componentFactory) {
            if($componentFactory instanceof ComponentFactoryInterface) {
                $this->addComponentFactory($componentFactory);
            }
        }
    }

    /**
     * @return int
     */
    public function getGridColumns()
    {
        return $this->gridColumns;
    }

    /**
     * @param int $gridColumns
     */
    public function setGridColumns($gridColumns)
    {
        $this->gridColumns = (int) $gridColumns;
    }

    /**
     * Adds an alisa for a component
     *
     * @param $alias
     * @param $tagName
     * @return $this
     */
    public function addAlias($alias, $tagName)
    {
        if($this->getComponentFactory($tagName)) {
            $this->alias[(string) $alias] = (string) $tagName;
        }
        return $this;
    }

    /**
     * removes an alias
     *
     * @param $alias string
     * @return $this
     */
    public function removeAlias($alias)
    {
        unset($this->alias[$alias]);
        return $this;
    }

    public function getAllAliasForTagName($tagName)
    {
        $allAlisa = array($tagName);
        foreach($this->alias as $alias => $tag) {
            if($tag == $tagName) {
                $allAlisa[] = $alias;
            }
        }
        return $allAlisa;
    }

    /**
     * returns a Component Factory for a given tag or alias
     *
     * @param $tagName
     * @return null|ComponentFactoryInterface
     */
    public function getComponentFactory($tagName)
    {
        //check for alias first
        if(isset($this->alias[$tagName])) {
            $tagName = $this->alias[$tagName];
        }

        if(
            isset($this->componentFactory[$tagName])
            && $this->componentFactory[$tagName] instanceof ComponentFactoryInterface
        ) {
            return $this->componentFactory[$tagName];
        }

        return null;
    }

    /**
     * add a Component Factory
     *
     * @param ComponentFactoryInterface $componentFactory
     * @return $this
     */
    public function addComponentFactory(ComponentFactoryInterface $componentFactory)
    {
        $this->componentFactory[$componentFactory->getName()] = $componentFactory;
        return $this;
    }

    /**
     * @return ComponentFactoryInterface[]
     */
    protected function getAllComponentFactories()
    {
        $factories = $this->componentFactory;
        foreach($this->alias as $alias => $name) {
            if($factory = $this->getComponentFactory($name)) {
                $factories[$alias] = $factory;
            }
        }
        return $factories;
    }

    /**
     * @param $html
     * @return string
     * @throws CircularException
     */
    public function releaseTheKraken($html)
    {
        $dom = new Dom();
        $dom->load((string) $html);

        $parseCounter = 0;
        while($this->parse($dom)) {
            $parseCounter++;
            if($parseCounter >= 100) {
                throw new CircularException('Inky reached max parsing runs of '.$parseCounter);
            }
        };
        return $dom->root->outerhtml;
    }

    protected function parse(Dom $dom)
    {
        $parseInComplete = false;
        foreach($this->getAllComponentFactories() as $tag => $factory) {
            $elements = $dom->getElementsByTag($tag);
            foreach($elements as $element) {
                /** @var HtmlNode $element */
                $newElement = $factory->parse($element, $this);
                if(!$newElement instanceof HtmlNode) {
                    continue;
                }
                // replace element
                if($newElement->id() !== $element->id()) {
                    $parseInComplete = true;
                    //replace element with new element
                    $parent = $element->getParent();
                    $siblings = $element->getParent()->getChildren();
                    foreach($siblings as $sibling) {
                        /** @var $sibling HtmlNode*/
                        $parent->removeChild($sibling->id());
                        if($sibling->id() === $element->id()) {
                            $parent->addChild($newElement);
                        }else {
                            $parent->addChild($sibling);
                        }
                    }
                }
            }
        }
        return $parseInComplete;
    }

}