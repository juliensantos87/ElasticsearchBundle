<?php

/*
 * This file is part of the ONGR package.
 *
 * (c) NFQ Technologies UAB <info@nfq.com>
 *
 * For the full copyright and license information, please view the LICENSE
 * file that was distributed with this source code.
 */

namespace ONGR\ElasticsearchBundle\Tests\Functional\DSL\Aggregation;

use ONGR\ElasticsearchBundle\DSL\Aggregation\PercentileRanksAggregation;
use ONGR\ElasticsearchBundle\ORM\Repository;
use ONGR\ElasticsearchBundle\Result\Aggregation\ValueAggregation;
use ONGR\ElasticsearchBundle\Test\AbstractElasticsearchTestCase;

class PercentileRanksAggregationTest extends AbstractElasticsearchTestCase
{
    /**
     * {@inheritdoc}
     */
    protected function getDataArray()
    {
        return [
            'default' => [
                'product' => [
                    [
                        '_id' => 1,
                        'title' => 'foo',
                        'price' => 10,
                    ],
                    [
                        '_id' => 2,
                        'title' => 'bar',
                        'price' => 15,
                    ],
                    [
                        '_id' => 3,
                        'title' => 'bar',
                        'price' => 25,
                    ],
                    [
                        '_id' => 4,
                        'title' => 'bar',
                        'price' => 25,
                    ],
                ],
            ],
        ];
    }

    /**
     * Data provider for testPercentileRanksAggregation().
     *
     * @return array
     */
    public function getPercentileRanksAggregationData()
    {
        $out = [];

        // Case #0 without any percent or compression.
        $aggregationData = ['field' => 'price', 'values' => [10, 30], 'compression' => null];
        $expectedResults = [
            '10.0' => 12.5,
            '30.0' => 100,
        ];
        $out[] = [$aggregationData, $expectedResults];

        // Case #2 with compression.
        $aggregationData = ['field' => 'price', 'values' => [10, 20, 90], 'compression' => 200];
        $expectedResults = [
            '10.0' => 12.5,
            '20.0' => 0,
            '90.0' => 100,
        ];
        $out[] = [$aggregationData, $expectedResults];

        return $out;
    }

    /**
     * Test for percentile ranks aggregation.
     *
     * @param array $aggData
     * @param array $expectedResults
     *
     * @dataProvider getPercentileRanksAggregationData()
     */
    public function testPercentileRanksAggregation($aggData, $expectedResults)
    {
        $repo = $this->getManager()->getRepository('AcmeTestBundle:Product');

        $aggregation = new PercentileRanksAggregation('test_agg');
        $aggregation->setField($aggData['field']);

        if ($aggData['values']) {
            $aggregation->setValues($aggData['values']);
        }

        if ($aggData['compression']) {
            $aggregation->setCompression($aggData['compression']);
        }
        $search = $repo->createSearch()->addAggregation($aggregation);
        $results = $repo->execute($search, Repository::RESULTS_OBJECT);

        /** @var ValueAggregation $result */
        $result = $results->getAggregations()['test_agg'];
        $this->assertEquals($expectedResults, $result->getValue()['values']);
    }

    /**
     * Tests percentile ranks aggregation using script instead of field.
     */
    public function testPercentileRanksWithScript()
    {
        $repository = $this->getManager()->getRepository('AcmeTestBundle:Product');

        $aggregation = new PercentileRanksAggregation('foo');
        $aggregation->setScript("doc['product.price'].value");
        $aggregation->setValues([10, 30]);
        $search = $repository
            ->createSearch()
            ->addAggregation($aggregation);
        /** @var ValueAggregation $result */
        $result = $repository->execute($search)->getAggregations()->find('foo');
        $expectedResults = [
            '10.0' => 12.5,
            '30.0' => 100,
        ];
        $this->assertEquals($expectedResults, $result->getValue()['values']);
    }
}
