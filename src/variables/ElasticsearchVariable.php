<?php
/**
 * Elasticsearch plugin for Craft CMS 3.x
 *
 * Bring the power of Elasticsearch to you Craft 3 CMS project
 *
 * @link      https://www.lahautesociete.com
 * @copyright Copyright (c) 2018 Alban Jubert
 */

namespace lhs\elasticsearch\variables;

use lhs\elasticsearch\Elasticsearch;

/**
 * Elasticsearch Variable
 *
 * Craft allows plugins to provide their own template variables, accessible from
 * the {{ craft }} global variable (e.g. {{ craft.elasticsearch }}).
 *
 * https://craftcms.com/docs/plugins/variables
 *
 * @author    Alban Jubert
 * @package   Elasticsearch
 * @since     1.0.0
 */
class ElasticsearchVariable
{
    // Public Methods
    // =========================================================================

    /**
     * Whatever you want to output to a Twig template can go into a Variable method.
     * You can have as many variable functions as you want.  From any Twig template,
     * call it like this:
     *
     *     {{ craft.elasticsearch.results }}
     *
     * Or, if your variable requires parameters from Twig:
     *
     *     {{ craft.elasticsearch.results(twigValue) }}
     *
     * @param null $optional
     * @return string
     */
    public function results($query)
    {
        return ElasticSearch::getInstance()->service->search($query);
    }
}
