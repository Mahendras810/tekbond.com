<?php

namespace Drupal\Tests\commerce_order\Kernel;

use Drupal\commerce\Context;
use Drupal\commerce_price\Price;
use Drupal\commerce_product\Entity\Product;
use Drupal\commerce_product\Entity\ProductVariation;
use Drupal\commerce_promotion\Entity\Promotion;
use Drupal\commerce_tax\Entity\TaxType;

/**
 * Tests the price calculator.
 *
 * @coversDefaultClass \Drupal\commerce_order\PriceCalculator
 *
 * @group commerce
 */
class PriceCalculatorTest extends OrderKernelTestBase {

  /**
   * The first variation.
   *
   * @var \Drupal\commerce_product\Entity\ProductVariationInterface
   */
  protected $firstVariation;

  /**
   * The second variation.
   *
   * @var \Drupal\commerce_product\Entity\ProductVariationInterface
   */
  protected $secondVariation;

  /**
   * The first test user.
   *
   * @var \Drupal\user\UserInterface
   */
  protected $firstUser;

  /**
   * The second test user.
   *
   * @var \Drupal\user\UserInterface
   */
  protected $secondUser;

  /**
   * The price calculator.
   *
   * @var \Drupal\commerce_order\PriceCalculatorInterface
   */
  protected $priceCalculator;

  /**
   * Modules to enable.
   *
   * @var array
   */
  protected static $modules = [
    'commerce_promotion',
    'commerce_tax',
    'commerce_order_test',
  ];

  /**
   * {@inheritdoc}
   */
  protected function setUp(): void {
    parent::setUp();

    $this->installEntitySchema('commerce_promotion');
    $this->installSchema('commerce_promotion', ['commerce_promotion_usage']);

    $this->store->set('tax_registrations', ['US']);
    $this->store->save();
    $promotion = Promotion::create([
      'name' => 'Promotion 1',
      'order_types' => ['default'],
      'stores' => [$this->store->id()],
      'status' => TRUE,
      'offer' => [
        'target_plugin_id' => 'order_item_percentage_off',
        'target_plugin_configuration' => [
          'percentage' => '0.5',
        ],
      ],
    ]);
    $promotion->save();

    // The default store is US-WI, so imagine that the US has VAT.
    TaxType::create([
      'id' => 'us_vat',
      'label' => 'US VAT',
      'plugin' => 'custom',
      'configuration' => [
        'display_inclusive' => TRUE,
        'rates' => [
          [
            'id' => 'standard',
            'label' => 'Standard',
            'percentage' => '0.2',
          ],
        ],
        'territories' => [
          ['country_code' => 'US', 'administrative_area' => 'WI'],
          ['country_code' => 'US', 'administrative_area' => 'SC'],
        ],
      ],
    ])->save();

    $first_variation = ProductVariation::create([
      'type' => 'default',
      'sku' => 'TEST_CALCULATED_PRICE',
      'status' => 1,
      'price' => new Price('3.00', 'USD'),
    ]);
    $first_variation->save();

    $second_variation = ProductVariation::create([
      'type' => 'default',
      'sku' => 'TEST_CALCULATED_PRICE2',
      'status' => 1,
      'price' => new Price('4.00', 'USD'),
    ]);
    $second_variation->save();

    $product = Product::create([
      'type' => 'default',
      'title' => 'Default testing product',
      'stores' => [$this->store->id()],
      'variations' => [$first_variation, $second_variation],
    ]);
    $product->save();

    $this->firstVariation = $this->reloadEntity($first_variation);
    $this->secondVariation = $this->reloadEntity($second_variation);

    $this->firstUser = $this->createUser([], NULL, FALSE, [
      'mail' => 'user1@example.com',
    ]);
    $this->secondUser = $this->createUser([], NULL, FALSE, [
      'mail' => 'user2@example.com',
    ]);

    $this->priceCalculator = $this->container->get('commerce_order.price_calculator');
  }

  /**
   * Tests the calculator.
   *
   * @covers ::calculate
   */
  public function testCalculation() {
    $first_context = new Context($this->firstUser, $this->store);
    $second_context = new Context($this->secondUser, $this->store);

    // No adjustment types specified.
    $result = $this->priceCalculator->calculate($this->firstVariation, 1, $first_context);
    $this->assertEquals(new Price('3.00', 'USD'), $result->getCalculatedPrice());
    $this->assertEquals(new Price('3.00', 'USD'), $result->getBasePrice());
    $this->assertEquals([], $result->getAdjustments());

    // Unknown adjustment type specified.
    $result = $this->priceCalculator->calculate($this->secondVariation, 1, $first_context, ['invalid']);
    $this->assertEquals(new Price('4.00', 'USD'), $result->getCalculatedPrice());
    $this->assertEquals(new Price('4.00', 'USD'), $result->getBasePrice());
    $this->assertEquals([], $result->getAdjustments());

    // Only tax.
    $result = $this->priceCalculator->calculate($this->firstVariation, 1, $first_context, ['tax']);
    $this->assertEquals(new Price('3.60', 'USD'), $result->getCalculatedPrice());
    $this->assertEquals(new Price('3.00', 'USD'), $result->getBasePrice());
    $this->assertCount(1, $result->getAdjustments());
    $adjustments = $result->getAdjustments();
    $first_adjustment = reset($adjustments);
    $this->assertEquals('tax', $first_adjustment->getType());
    $this->assertEquals(new Price('0.60', 'USD'), $first_adjustment->getAmount());

    $result = $this->priceCalculator->calculate($this->secondVariation, 1, $first_context, ['tax']);
    $this->assertEquals(new Price('4.80', 'USD'), $result->getCalculatedPrice());
    $this->assertEquals(new Price('4.00', 'USD'), $result->getBasePrice());
    $this->assertCount(1, $result->getAdjustments());
    $adjustments = $result->getAdjustments();
    $first_adjustment = reset($adjustments);
    $this->assertEquals('tax', $first_adjustment->getType());
    $this->assertEquals(new Price('0.80', 'USD'), $first_adjustment->getAmount());

    // Tax and promotions.
    $result = $this->priceCalculator->calculate($this->firstVariation, 1, $first_context, ['tax', 'promotion']);
    $this->assertEquals(new Price('1.80', 'USD'), $result->getCalculatedPrice());
    $this->assertEquals(new Price('3.00', 'USD'), $result->getBasePrice());
    $this->assertCount(2, $result->getAdjustments());
    $adjustments = $result->getAdjustments();
    $first_adjustment = reset($adjustments);
    $this->assertEquals('promotion', $first_adjustment->getType());
    $this->assertEquals(new Price('-1.80', 'USD'), $first_adjustment->getAmount());
    $second_adjustment = end($adjustments);
    $this->assertEquals('tax', $second_adjustment->getType());
    $this->assertEquals(new Price('0.60', 'USD'), $second_adjustment->getAmount());

    $result = $this->priceCalculator->calculate($this->secondVariation, 1, $first_context, ['tax', 'promotion']);
    $this->assertEquals(new Price('2.40', 'USD'), $result->getCalculatedPrice());
    $this->assertEquals(new Price('4.00', 'USD'), $result->getBasePrice());
    $this->assertCount(2, $result->getAdjustments());
    $adjustments = $result->getAdjustments();
    $first_adjustment = reset($adjustments);
    $this->assertEquals('promotion', $first_adjustment->getType());
    $this->assertEquals(new Price('-2.40', 'USD'), $first_adjustment->getAmount());
    $second_adjustment = end($adjustments);
    $this->assertEquals('tax', $second_adjustment->getType());
    $this->assertEquals(new Price('0.80', 'USD'), $second_adjustment->getAmount());

    // User-specific adjustment added by TestAdjustmentProcessor.
    $result = $this->priceCalculator->calculate($this->secondVariation, 1, $second_context, ['test_adjustment_type']);
    $this->assertEquals(new Price('6.00', 'USD'), $result->getCalculatedPrice());
    $this->assertEquals(new Price('4.00', 'USD'), $result->getBasePrice());
    $this->assertCount(1, $result->getAdjustments());
    $adjustments = $result->getAdjustments();
    $first_adjustment = reset($adjustments);
    $this->assertEquals('test_adjustment_type', $first_adjustment->getType());
    $this->assertEquals(new Price('2.00', 'USD'), $first_adjustment->getAmount());
  }

}
