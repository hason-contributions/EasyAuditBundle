<?php

/*
 * This file is part of the XiideaEasyAuditBundle package.
 *
 * (c) Xiidea <http://www.xiidea.net>
 *
 * This source file is subject to the MIT license that is bundled
 * with this source code in the file LICENSE.
 */

namespace Xiidea\EasyAuditBundle\DependencyInjection;

use Symfony\Component\Config\Definition\Builder\ArrayNodeDefinition;
use Symfony\Component\Config\Definition\Builder\TreeBuilder;
use Symfony\Component\Config\Definition\ConfigurationInterface;
use Symfony\Component\Config\Definition\Exception\InvalidConfigurationException;

/**
 * This is the class that validates and merges configuration from your app/config files.
 *
 * To learn more see {@link http://symfony.com/doc/current/cookbook/bundles/extension.html#cookbook-bundles-extension-config-class}
 */
class Configuration implements ConfigurationInterface
{
    const ROOT_NODE_NAME = 'xiidea_easy_audit';

    /**
     * {@inheritdoc}
     */
    public function getConfigTreeBuilder()
    {
        $treeBuilder = new TreeBuilder(self::ROOT_NODE_NAME);

        $rootNode = $this->getRootNode($treeBuilder);

        $this->addRequiredConfigs($rootNode);
        $this->addDefaultServices($rootNode);
        $this->addOptionalConfigs($rootNode);
        $this->addChannelHandlers($rootNode);

        return $treeBuilder;
    }

    /**
     * @param ArrayNodeDefinition $rootNode
     */
    private function addRequiredConfigs(ArrayNodeDefinition $rootNode)
    {
        $rootNode
            ->children()
                ->scalarNode('user_property')->isRequired()->end()
                ->scalarNode('audit_log_class')->cannotBeOverwritten()->isRequired()->cannotBeEmpty()->end()
                ->scalarNode('entity_class')->cannotBeOverwritten()
//                    ->setDeprecated('The "%node%" option is deprecated since XiideaEasyAuditBundle 1.4.10. and will not be supported anymore in 2.0. Use "audit_log_class" instead.')
                ->end()
            ->end();
    }

    /**
     * @param ArrayNodeDefinition $rootNode
     */
    private function addDefaultServices(ArrayNodeDefinition $rootNode)
    {
        $rootNode
            ->children()
                ->scalarNode('resolver')->defaultValue('xiidea.easy_audit.default_event_resolver')->end()
                ->scalarNode('doctrine_event_resolver')
                    ->defaultValue(null)
                ->end()
                ->scalarNode('entity_event_resolver')
                    ->defaultValue(null)
//                    ->setDeprecated('The "%node%" option is deprecated since XiideaEasyAuditBundle 1.4.10. and will not be supported anymore in 2.0. Use "doctrine_event_resolver" instead.')
                ->end()
                ->booleanNode('default_logger')->defaultValue(true)->end()
            ->end();
    }

    /**
     * @param ArrayNodeDefinition $rootNode
     */
    private function addOptionalConfigs(ArrayNodeDefinition $rootNode)
    {
        $rootNode
            ->children()
                ->variableNode('doctrine_objects')
                    ->defaultValue(array())
                ->end()
                ->variableNode('doctrine_entities')
                    ->defaultValue(array())
//                    ->setDeprecated('The "%node%" option is deprecated since XiideaEasyAuditBundle 1.4.10. and will not be supported anymore in 2.0. Use "doctrine_objects" instead.')
                ->end()
                ->variableNode('events')->defaultValue(array())->end()
                ->variableNode('custom_resolvers')->defaultValue(array())->end()
            ->end();
    }

    /**
     * @param ArrayNodeDefinition $rootNode
     */
    private function addChannelHandlers(ArrayNodeDefinition $rootNode)
    {
        $rootNode
            ->fixXmlConfig('loggerChannel')
            ->children()
                ->arrayNode('logger_channel')
                    ->canBeUnset()
                    ->useAttributeAsKey('name')
                    ->prototype('array')
                    ->fixXmlConfig('channel', 'elements')
                        ->canBeUnset()
                        ->beforeNormalization()->ifString()->then($this->changeToArrayFromString())->end()
                        ->beforeNormalization()->ifTrue($this->isIndexedArray())->then($this->changeToAssoc())->end()
                        ->validate()->ifTrue($this->isEmpty())->thenUnset()->end()
                        ->validate()->always($this->getChannelTypeValidator())->end()
                        ->children()
                            ->scalarNode('type')->validate()
                                ->ifNotInArray(array('inclusive', 'exclusive'))
                                ->thenInvalid('The type of channels has to be inclusive or exclusive')->end()->end()
                            ->arrayNode('elements')->prototype('scalar')->end()->end()->end()
                        ->end()
                    ->end()
                ->end()
            ->end();
    }

    /**
     * @return \Closure
     */
    private function getChannelTypeValidator()
    {
        return function ($v) {
            $isExclusiveList = isset($v['type']) ? 'exclusive' === $v['type'] : null;
            $elements = array();

            foreach ($v['elements'] as $element) {
                Configuration::appendChannelTypes($element, $isExclusiveList, $elements);
            }

            return array('type' => $isExclusiveList ? 'exclusive' : 'inclusive', 'elements' => $elements);
        };
    }

    /**
     * @param bool $invalid
     *
     * @throws InvalidConfigurationException
     */
    public static function throwExceptionOnInvalid($invalid)
    {
        if (!$invalid) {
            return;
        }

        throw new InvalidConfigurationException(
            'Cannot combine exclusive/inclusive definitions in channels list'
        );
    }

    public static function appendChannelTypes($element, &$isExclusiveList, &$elements = array())
    {
        $isExclusiveItem = 0 === strpos($element, '!');

        self::throwExceptionOnInvalid(!$isExclusiveItem === $isExclusiveList);

        $elements[] = $isExclusiveItem ? substr($element, 1) : $element;
        $isExclusiveList = $isExclusiveItem;
    }

    /**
     * @return \Closure
     */
    private function isIndexedArray()
    {
        return function ($v) {
            return is_array($v) && is_numeric(key($v));
        };
    }

    /**
     * @return \Closure
     */
    private function changeToAssoc()
    {
        return function ($v) {
            return array('elements' => $v);
        };
    }

    /**
     * @return \Closure
     */
    private function changeToArrayFromString()
    {
        return function ($v) {
            return array('elements' => array($v));
        };
    }

    /**
     * @return \Closure
     */
    private function isEmpty()
    {
        return function ($v) {
            return empty($v);
        };
    }

    /**
     * @param TreeBuilder $treeBuilder
     * @return ArrayNodeDefinition|\Symfony\Component\Config\Definition\Builder\NodeDefinition
     */
    protected function getRootNode($treeBuilder)
    {
        if (method_exists($treeBuilder, 'getRootNode')) {
            return $treeBuilder->getRootNode();
        }

        return $treeBuilder->root(self::ROOT_NODE_NAME);
    }
}
