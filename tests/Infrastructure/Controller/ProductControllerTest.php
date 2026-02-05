<?php

namespace App\Tests\Infrastructure\Controller;

use App\Domain\Entity\Product;
use App\Domain\ValueObject\Money;
use Symfony\Bundle\FrameworkBundle\Test\WebTestCase;

class ProductControllerTest extends WebTestCase
{
    public function testListProducts(): void
    {
        $client = static::createClient();
        
        $client->request('GET', '/api/products');

        $this->assertResponseIsSuccessful();
        $this->assertResponseHeaderSame('Content-Type', 'application/json');
        
        $data = json_decode($client->getResponse()->getContent(), true);
        $this->assertIsArray($data);
    }

    public function testShowProduct(): void
    {
        $client = static::createClient();
        
        // First, get the list of products to get a valid ID
        $client->request('GET', '/api/products');
        $products = json_decode($client->getResponse()->getContent(), true);
        
        if (empty($products)) {
            $this->markTestSkipped('No products available for testing');
        }
        
        $productId = $products[0]['id'];
        
        // Now test showing a specific product
        $client->request('GET', '/api/products/' . $productId);
        
        $this->assertResponseIsSuccessful();
        $data = json_decode($client->getResponse()->getContent(), true);
        
        $this->assertArrayHasKey('id', $data);
        $this->assertArrayHasKey('name', $data);
        $this->assertArrayHasKey('price', $data);
        $this->assertArrayHasKey('stock', $data);
        $this->assertArrayHasKey('category', $data);
    }

    public function testShowNonExistentProduct(): void
    {
        $client = static::createClient();
        
        $client->request('GET', '/api/products/invalid-uuid');

        $this->assertResponseStatusCodeSame(404);
        
        $data = json_decode($client->getResponse()->getContent(), true);
        $this->assertArrayHasKey('error', $data);
        $this->assertEquals('Product not found', $data['error']);
    }

    public function testSearchProductsByQuery(): void
    {
        $client = static::createClient();
        
        $client->request('GET', '/api/products?q=test');

        $this->assertResponseIsSuccessful();
        $data = json_decode($client->getResponse()->getContent(), true);
        $this->assertIsArray($data);
    }

    public function testSearchProductsByCategory(): void
    {
        $client = static::createClient();
        
        $client->request('GET', '/api/products?category=Electronics');

        $this->assertResponseIsSuccessful();
        $data = json_decode($client->getResponse()->getContent(), true);
        $this->assertIsArray($data);
    }

    public function testSearchProductsByPriceRange(): void
    {
        $client = static::createClient();
        
        $client->request('GET', '/api/products?minPrice=10&maxPrice=50');

        $this->assertResponseIsSuccessful();
        $data = json_decode($client->getResponse()->getContent(), true);
        $this->assertIsArray($data);
    }
}
