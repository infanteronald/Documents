<?php
/**
 * Datos de muestra para pruebas del sistema de pedidos
 */

class TestFixtures {
    
    public static function getSampleProducts() {
        return [
            [
                'id' => 1,
                'nombre' => 'Camisa Polo Azul',
                'precio' => 45000,
                'categoria' => 'camisas',
                'talla' => 'M',
                'descripcion' => 'Camisa polo de algodón azul',
                'stock' => 10,
                'created_for_test' => 1
            ],
            [
                'id' => 2,
                'nombre' => 'Pantalón Jean',
                'precio' => 85000,
                'categoria' => 'pantalones',
                'talla' => 'L',
                'descripcion' => 'Pantalón jean clásico',
                'stock' => 5,
                'created_for_test' => 1
            ],
            [
                'id' => 3,
                'nombre' => 'Zapatos Deportivos',
                'precio' => 120000,
                'categoria' => 'calzado',
                'talla' => '42',
                'descripcion' => 'Zapatos deportivos cómodos',
                'stock' => 8,
                'created_for_test' => 1
            ]
        ];
    }
    
    public static function getSampleCustomers() {
        return [
            [
                'id' => 1,
                'nombre' => 'Ana García',
                'email' => 'ana.garcia@test.com',
                'telefono' => '+57 301 234 5678',
                'direccion' => 'Calle 123 #45-67, Bogotá',
                'ciudad' => 'Bogotá',
                'created_for_test' => 1
            ],
            [
                'id' => 2,
                'nombre' => 'Carlos López',
                'email' => 'carlos.lopez@test.com',
                'telefono' => '+57 302 345 6789',
                'direccion' => 'Carrera 89 #12-34, Medellín',
                'ciudad' => 'Medellín',
                'created_for_test' => 1
            ],
            [
                'id' => 3,
                'nombre' => 'María Rodríguez',
                'email' => 'maria.rodriguez@test.com',
                'telefono' => '+57 303 456 7890',
                'direccion' => 'Avenida 56 #78-90, Cali',
                'ciudad' => 'Cali',
                'created_for_test' => 1
            ]
        ];
    }
    
    public static function getSampleOrders() {
        return [
            [
                'id' => 1,
                'cliente_id' => 1,
                'total' => 130000,
                'estado' => 'pendiente',
                'metodo_pago' => 'bold',
                'fecha_pedido' => date('Y-m-d H:i:s'),
                'productos' => [
                    ['producto_id' => 1, 'cantidad' => 1, 'precio' => 45000],
                    ['producto_id' => 2, 'cantidad' => 1, 'precio' => 85000]
                ],
                'created_for_test' => 1
            ],
            [
                'id' => 2,
                'cliente_id' => 2,
                'total' => 120000,
                'estado' => 'pagado',
                'metodo_pago' => 'bold',
                'fecha_pedido' => date('Y-m-d H:i:s', strtotime('-1 day')),
                'productos' => [
                    ['producto_id' => 3, 'cantidad' => 1, 'precio' => 120000]
                ],
                'created_for_test' => 1
            ],
            [
                'id' => 3,
                'cliente_id' => 3,
                'total' => 45000,
                'estado' => 'enviado',
                'metodo_pago' => 'efectivo',
                'fecha_pedido' => date('Y-m-d H:i:s', strtotime('-2 days')),
                'productos' => [
                    ['producto_id' => 1, 'cantidad' => 1, 'precio' => 45000]
                ],
                'created_for_test' => 1
            ]
        ];
    }
    
    public static function getBoldWebhookPayload($orderId = 1, $status = 'approved') {
        return json_encode([
            'type' => 'payment.approved',
            'data' => [
                'id' => 'txn_test_' . uniqid(),
                'amount' => 130000,
                'currency' => 'COP',
                'status' => $status,
                'reference' => 'ORDER_' . $orderId,
                'customer' => [
                    'email' => 'test@example.com',
                    'name' => 'Cliente Test'
                ],
                'payment_method' => [
                    'type' => 'CARD',
                    'brand' => 'VISA',
                    'last_four' => '1234'
                ],
                'created_at' => date('c'),
                'updated_at' => date('c')
            ],
            'created_at' => date('c')
        ]);
    }
    
    public static function getSampleComprobante() {
        return [
            'nombre_archivo' => 'comprobante_test_' . time() . '.jpg',
            'tipo_archivo' => 'image/jpeg',
            'tamano' => 1024,
            'contenido_base64' => base64_encode('fake_image_content_for_testing'),
            'created_for_test' => 1
        ];
    }
    
    public static function getEmailTemplateData() {
        return [
            'cliente_nombre' => 'Cliente Test',
            'cliente_email' => 'test@example.com',
            'pedido_id' => 1,
            'pedido_total' => '$130.000',
            'fecha_pedido' => date('d/m/Y H:i'),
            'productos' => [
                'Camisa Polo Azul - Talla M',
                'Pantalón Jean - Talla L'
            ]
        ];
    }
}
