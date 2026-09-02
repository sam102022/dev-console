<?php
declare(strict_types=1);

namespace App\tests;

use App\ServiceFactory;
use App\tests\AbstractTestCase;
use PHPUnit\Framework\Attributes\DataProvider;

/**
 * Classe ServiceFactoryTest
 *
 * Tests unitaires paramétrés pour le pont ServiceFactory.
 */
class ServiceFactoryTest extends AbstractTestCase
{
    protected function setUp(): void
    {
        parent::setUp();
        // Réinitialiser les closures statiques si nécessaire
        $ref = new \ReflectionClass(ServiceFactory::class);
        $prop = $ref->getProperty('closures');
        $prop->setValue(null, []);
    }

    /**
     * Teste la création réussie de services enregistrés.
     */
    #[DataProvider('serviceDataProvider')]
    public function testCreateServiceSuccess(string $serviceId, callable $closure, mixed $expectedResult): void
    {
        ServiceFactory::register($serviceId, $closure);
        
        $containerMock = new \stdClass();
        $result = ServiceFactory::create($serviceId, $containerMock);
        
        $this->assertEquals($expectedResult, $result);
    }

    /**
     * Fournisseur de données pour le test de création de service réussie.
     */
    public static function serviceDataProvider(): array
    {
        return [
            'service_simple_string' => [
                'my_string_service',
                fn($c) => 'hello_world',
                'hello_world'
            ],
            'service_array' => [
                'my_array_service',
                fn($c) => ['a', 'b', 'c'],
                ['a', 'b', 'c']
            ],
            'service_object' => [
                'my_object_service',
                fn($c) => (object)['foo' => 'bar'],
                (object)['foo' => 'bar']
            ],
            'service_using_container' => [
                'my_container_service',
                fn($c) => 'container_class_' . get_class($c),
                'container_class_stdClass'
            ],
        ];
    }

    /**
     * Teste que la création d'un service non enregistré lève une exception.
     */
    public function testCreateUnregisteredServiceThrowsException(): void
    {
        $this->expectException(\RuntimeException::class);
        $this->expectExceptionMessage("Aucune usine de création enregistrée pour le service : non_existent_service");
        
        ServiceFactory::create('non_existent_service', new \stdClass());
    }
}
