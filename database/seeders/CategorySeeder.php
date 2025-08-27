<?php

declare(strict_types=1);

namespace Database\Seeders;

use App\Models\Category;
use Illuminate\Database\Seeder;
use Illuminate\Support\Str;

class CategorySeeder extends Seeder
{
    /**
     * Run the database seeds.
     */
    public function run(): void
    {
        // Create main categories with predefined data
        $mainCategories = [
            [
                'name' => 'Web Development',
                'icon' => 'code',
                'description' => 'Website development, web applications, and web-related programming services.',
                'children' => [
                    'Frontend Development',
                    'Backend Development',
                    'Full Stack Development',
                    'WordPress Development',
                    'E-commerce Development',
                ],
            ],
            [
                'name' => 'Mobile Development',
                'icon' => 'mobile',
                'description' => 'Mobile app development for iOS, Android, and cross-platform solutions.',
                'children' => [
                    'iOS Development',
                    'Android Development',
                    'React Native',
                    'Flutter Development',
                    'Mobile UI/UX',
                ],
            ],
            [
                'name' => 'Design & Creative',
                'icon' => 'palette',
                'description' => 'Graphic design, branding, and creative services.',
                'children' => [
                    'Logo Design',
                    'Graphic Design',
                    'UI/UX Design',
                    'Brand Identity',
                    'Print Design',
                ],
            ],
            [
                'name' => 'Writing & Content',
                'icon' => 'edit',
                'description' => 'Content creation, copywriting, and editorial services.',
                'children' => [
                    'Blog Writing',
                    'Copywriting',
                    'Technical Writing',
                    'Content Strategy',
                    'Proofreading',
                ],
            ],
            [
                'name' => 'Digital Marketing',
                'icon' => 'megaphone',
                'description' => 'Online marketing, SEO, social media, and advertising services.',
                'children' => [
                    'SEO Services',
                    'Social Media Marketing',
                    'PPC Advertising',
                    'Email Marketing',
                    'Content Marketing',
                ],
            ],
            [
                'name' => 'Data & Analytics',
                'icon' => 'chart-bar',
                'description' => 'Data analysis, data entry, and business intelligence services.',
                'children' => [
                    'Data Entry',
                    'Data Analysis',
                    'Business Intelligence',
                    'Database Management',
                    'Excel Services',
                ],
            ],
            [
                'name' => 'Video & Animation',
                'icon' => 'video',
                'description' => 'Video production, editing, and animation services.',
                'children' => [
                    'Video Editing',
                    '2D Animation',
                    '3D Animation',
                    'Motion Graphics',
                    'Video Production',
                ],
            ],
            [
                'name' => 'Translation & Languages',
                'icon' => 'language',
                'description' => 'Translation, interpretation, and language services.',
                'children' => [
                    'Document Translation',
                    'Website Translation',
                    'Interpretation',
                    'Localization',
                    'Language Tutoring',
                ],
            ],
            [
                'name' => 'Business Services',
                'icon' => 'briefcase',
                'description' => 'Business consulting, virtual assistance, and professional services.',
                'children' => [
                    'Virtual Assistant',
                    'Business Consulting',
                    'Project Management',
                    'Market Research',
                    'Business Planning',
                ],
            ],
            [
                'name' => 'Photography',
                'icon' => 'camera',
                'description' => 'Photography services for products, events, and portraits.',
                'children' => [
                    'Product Photography',
                    'Event Photography',
                    'Portrait Photography',
                    'Photo Editing',
                    'Stock Photography',
                ],
            ],
        ];

        foreach ($mainCategories as $index => $categoryData) {
            // Create main category
            $category = Category::create([
                'name' => $categoryData['name'],
                'slug' => Str::slug($categoryData['name']),
                'description' => $categoryData['description'],
                'icon' => $categoryData['icon'],
                'parent_id' => null,
                'lft' => ($index * 20) + 1,
                'rgt' => ($index * 20) + 20,
                'depth' => 0,
                'is_active' => true,
            ]);

            // Create child categories
            foreach ($categoryData['children'] as $childIndex => $childName) {
                Category::create([
                    'name' => $childName,
                    'slug' => Str::slug($childName),
                    'description' => "Specialized services in {$childName}",
                    'icon' => $categoryData['icon'],
                    'parent_id' => $category->id,
                    'lft' => ($index * 20) + 2 + ($childIndex * 3),
                    'rgt' => ($index * 20) + 3 + ($childIndex * 3),
                    'depth' => 1,
                    'is_active' => true,
                ]);
            }
        }

        // Create some additional random categories
        Category::factory(10)->create();
    }
}
