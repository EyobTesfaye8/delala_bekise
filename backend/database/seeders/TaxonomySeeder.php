<?php

namespace Database\Seeders;

use App\Models\Category;
use Illuminate\Database\Seeder;

class TaxonomySeeder extends Seeder
{
    /**
     * Run the database seeds.
     */
    public function run(): void
    {
        // ==========================================
        // 1. Real Estate Taxonomy
        // ==========================================
        $realEstate = Category::updateOrCreate(
            ['slug' => 'real-estate'],
            [
                'parent_id' => null,
                'name' => 'Real Estate',
                'description' => 'Residential, commercial, and land properties across Ethiopia.',
                'attributes_schema' => null,
                'json_schema' => null,
                'is_active' => true,
                'is_leaf' => false,
                'sort_order' => 1,
            ]
        );

        $residentialRentalSchema = [
            '$schema' => 'http://json-schema.org/draft-07/schema#',
            'type' => 'object',
            'title' => 'Residential Rental Specification',
            'properties' => [
                'bedrooms' => [
                    'type' => 'integer',
                    'minimum' => 0,
                    'description' => 'Number of bedrooms',
                ],
                'bathrooms' => [
                    'type' => 'number',
                    'minimum' => 0,
                    'description' => 'Number of bathrooms',
                ],
                'furnished' => [
                    'type' => 'boolean',
                    'description' => 'Whether the residential unit is furnished',
                ],
                'backup_generator' => [
                    'type' => 'boolean',
                    'description' => 'Presence of dedicated standby backup generator',
                ],
                'parking' => [
                    'type' => 'boolean',
                    'description' => 'Dedicated on-site parking spot available',
                ],
            ],
            'required' => ['bedrooms', 'bathrooms', 'furnished', 'backup_generator', 'parking'],
        ];

        Category::updateOrCreate(
            ['slug' => 'residential-rental'],
            [
                'parent_id' => $realEstate->id,
                'name' => 'Residential Rental',
                'description' => 'Furnished and unfurnished villas, apartments, and condominiums for rent.',
                'attributes_schema' => $residentialRentalSchema,
                'json_schema' => $residentialRentalSchema,
                'is_active' => true,
                'is_leaf' => true,
                'sort_order' => 1,
            ]
        );

        $commercialLeaseSchema = [
            '$schema' => 'http://json-schema.org/draft-07/schema#',
            'type' => 'object',
            'title' => 'Commercial Lease Specification',
            'properties' => [
                'square_meters' => [
                    'type' => 'number',
                    'minimum' => 1,
                    'description' => 'Total usable floor space in square meters',
                ],
                'floor_number' => [
                    'type' => 'integer',
                    'description' => 'Floor level where commercial space is located (0 for ground floor)',
                ],
                'elevator' => [
                    'type' => 'boolean',
                    'description' => 'Functional elevator access to the floor',
                ],
            ],
            'required' => ['square_meters', 'floor_number', 'elevator'],
        ];

        Category::updateOrCreate(
            ['slug' => 'commercial-lease'],
            [
                'parent_id' => $realEstate->id,
                'name' => 'Commercial Lease',
                'description' => 'Offices, showrooms, retail shops, and warehouses for commercial lease.',
                'attributes_schema' => $commercialLeaseSchema,
                'json_schema' => $commercialLeaseSchema,
                'is_active' => true,
                'is_leaf' => true,
                'sort_order' => 2,
            ]
        );

        $residentialSaleSchema = [
            '$schema' => 'http://json-schema.org/draft-07/schema#',
            'type' => 'object',
            'title' => 'Residential Sale Specification',
            'properties' => [
                'bedrooms' => [
                    'type' => 'integer',
                    'minimum' => 0,
                    'description' => 'Number of bedrooms',
                ],
                'bathrooms' => [
                    'type' => 'number',
                    'minimum' => 0,
                    'description' => 'Number of bathrooms',
                ],
                'square_meters' => [
                    'type' => 'number',
                    'minimum' => 1,
                    'description' => 'Built or plot area in square meters',
                ],
                'property_type' => [
                    'type' => 'string',
                    'enum' => ['villa', 'apartment', 'condominium', 'townhouse'],
                    'description' => 'Type of residential building',
                ],
                'title_deed_verified' => [
                    'type' => 'boolean',
                    'description' => 'Official certified title deed (carta) verified',
                ],
            ],
            'required' => ['bedrooms', 'bathrooms', 'square_meters'],
        ];

        Category::updateOrCreate(
            ['slug' => 'residential-sale'],
            [
                'parent_id' => $realEstate->id,
                'name' => 'Residential Sale',
                'description' => 'Villas, modern apartments, and real estate developments for purchase.',
                'attributes_schema' => $residentialSaleSchema,
                'json_schema' => $residentialSaleSchema,
                'is_active' => true,
                'is_leaf' => true,
                'sort_order' => 3,
            ]
        );

        // ==========================================
        // 2. Vehicles Taxonomy
        // ==========================================
        $vehicles = Category::updateOrCreate(
            ['slug' => 'vehicles'],
            [
                'parent_id' => null,
                'name' => 'Vehicles',
                'description' => 'Automobiles, commercial vehicles, trucks, and machinery.',
                'attributes_schema' => null,
                'json_schema' => null,
                'is_active' => true,
                'is_leaf' => false,
                'sort_order' => 2,
            ]
        );

        $passengerCarsSchema = [
            '$schema' => 'http://json-schema.org/draft-07/schema#',
            'type' => 'object',
            'title' => 'Passenger Car Specification',
            'properties' => [
                'make' => [
                    'type' => 'string',
                    'minLength' => 2,
                    'description' => 'Manufacturer make (e.g. Toyota, Hyundai, BYD, Suzuki)',
                ],
                'model' => [
                    'type' => 'string',
                    'minLength' => 1,
                    'description' => 'Model name (e.g. Corolla, Vitz, Tucson, Atto 3)',
                ],
                'year' => [
                    'type' => 'integer',
                    'minimum' => 1970,
                    'maximum' => 2030,
                    'description' => 'Year of manufacture',
                ],
                'transmission' => [
                    'type' => 'string',
                    'enum' => ['automatic', 'manual'],
                    'description' => 'Gearbox transmission type',
                ],
                'fuel_type' => [
                    'type' => 'string',
                    'enum' => ['petrol', 'diesel', 'electric', 'hybrid'],
                    'description' => 'Primary propulsion fuel type',
                ],
                'mileage' => [
                    'type' => 'integer',
                    'minimum' => 0,
                    'description' => 'Odometer reading in kilometers',
                ],
            ],
            'required' => ['make', 'model', 'year', 'transmission', 'fuel_type', 'mileage'],
        ];

        Category::updateOrCreate(
            ['slug' => 'passenger-cars'],
            [
                'parent_id' => $vehicles->id,
                'name' => 'Passenger Cars',
                'description' => 'Sedans, compact hatchbacks, SUVs, and personal passenger automobiles.',
                'attributes_schema' => $passengerCarsSchema,
                'json_schema' => $passengerCarsSchema,
                'is_active' => true,
                'is_leaf' => true,
                'sort_order' => 1,
            ]
        );

        $commercialVehiclesSchema = [
            '$schema' => 'http://json-schema.org/draft-07/schema#',
            'type' => 'object',
            'title' => 'Commercial Vehicle Specification',
            'properties' => [
                'vehicle_type' => [
                    'type' => 'string',
                    'enum' => ['pickup', 'truck', 'van', 'bus', 'trailer', 'machinery'],
                    'description' => 'Category of commercial vehicle',
                ],
                'payload_capacity_tons' => [
                    'type' => 'number',
                    'minimum' => 0,
                    'description' => 'Rated payload cargo capacity in metric tons',
                ],
                'year' => [
                    'type' => 'integer',
                    'minimum' => 1970,
                    'maximum' => 2030,
                    'description' => 'Year of manufacture',
                ],
                'transmission' => [
                    'type' => 'string',
                    'enum' => ['automatic', 'manual'],
                    'description' => 'Transmission type',
                ],
                'fuel_type' => [
                    'type' => 'string',
                    'enum' => ['diesel', 'petrol', 'electric'],
                    'description' => 'Fuel type',
                ],
                'mileage' => [
                    'type' => 'integer',
                    'minimum' => 0,
                    'description' => 'Odometer reading in kilometers',
                ],
            ],
            'required' => ['vehicle_type', 'payload_capacity_tons', 'year', 'transmission', 'fuel_type'],
        ];

        Category::updateOrCreate(
            ['slug' => 'commercial-vehicles'],
            [
                'parent_id' => $vehicles->id,
                'name' => 'Commercial Vehicles',
                'description' => 'Pickups, cargo trucks, transport vans, buses, and commercial transport vehicles.',
                'attributes_schema' => $commercialVehiclesSchema,
                'json_schema' => $commercialVehiclesSchema,
                'is_active' => true,
                'is_leaf' => true,
                'sort_order' => 2,
            ]
        );
    }
}
