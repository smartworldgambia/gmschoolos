<?php

namespace App\Services\School;

use App\Models\Level;
use App\Models\NewsPost;
use App\Models\School;
use App\Services\Scale\CacheTuner;
use Illuminate\Support\Str;

/**
 * Seeds a dedicated public landing page for a newly registered school,
 * tailored by school category / type.
 */
class SchoolLandingProvisioner
{
    public function __construct(protected CacheTuner $cache) {}

    public function provision(School $school): School
    {
        $template = $this->templateFor($school->category ?? 'combined');

        $school->fill([
            'tagline' => $template['tagline'],
            'about' => str_replace('{name}', $school->name, $template['about']),
            'primary_color' => $template['primary_color'],
            'accent_color' => $template['accent_color'],
            'website_published' => true,
            'settings' => array_merge($school->settings ?? [], [
                'landing' => [
                    'template' => $template['key'],
                    'category_label' => $template['label'],
                    'hero_eyebrow' => $template['hero_eyebrow'],
                    'hero_headline' => $template['hero_headline'],
                    'hero_body' => str_replace('{name}', $school->name, $template['hero_body']),
                    'hero_image' => $template['hero_image'],
                    'hero_slides' => $template['hero_slides'] ?? $this->defaultHeroSlides($template['hero_image']),
                    'cta_primary' => $template['cta_primary'],
                    'cta_secondary' => $template['cta_secondary'],
                    'levels_heading' => $template['levels_heading'],
                    'levels_intro' => str_replace('{name}', $school->name, $template['levels_intro']),
                    'about_headline' => $template['about_headline'],
                    'about_image' => $template['about_image'],
                    'admissions_title' => $template['admissions_title'],
                    'admissions_body' => str_replace('{name}', $school->name, $template['admissions_body']),
                    'admissions_steps' => $template['admissions_steps'],
                    'contact_blurb' => str_replace('{name}', $school->name, $template['contact_blurb']),
                    'features' => $template['features'],
                    'level_cards' => $this->levelCardsFor($template),
                    'provisioned_at' => now()->toIso8601String(),
                ],
            ]),
        ])->save();

        $this->seedLevels($school, $template['levels']);
        $this->seedNewsPosts($school, $template);

        $this->cache->forgetPublicSite($school->id);

        return $school->fresh();
    }

    /**
     * @return array{
     *   key: string,
     *   label: string,
     *   tagline: string,
     *   about: string,
     *   hero_eyebrow: string,
     *   hero_headline: string,
     *   hero_body: string,
     *   hero_image: string,
     *   cta_primary: string,
     *   cta_secondary: string,
     *   levels_heading: string,
     *   levels_intro: string,
     *   primary_color: string,
     *   accent_color: string,
     *   features: list<array{key: string, icon: string, title: string, subtitle: string}>,
     *   levels: list<array{name: string, code: string, description?: string, image?: string}>,
     *   news_title: string,
     *   news_excerpt: string,
     *   news_body: string
     * }
     */
    public function templateFor(string $category): array
    {
        $templates = $this->templates();

        return $this->withSectionDefaults($templates[$category] ?? $templates['combined']);
    }

    /**
     * @param  array<string, mixed>  $template
     * @return list<array{name: string, code: string, description: string, image: string}>
     */
    protected function levelCardsFor(array $template): array
    {
        $cards = [];
        foreach ($template['levels'] as $level) {
            $cards[] = [
                'name' => $level['name'],
                'code' => $level['code'],
                'description' => $level['description'] ?? '',
                'image' => $level['image'] ?? '/images/marketing/hero-students.jpg',
            ];
        }

        return $cards;
    }

    /**
     * @return list<array{key: string, icon: string, title: string, subtitle: string}>
     */
    protected function defaultFeatures(): array
    {
        return [
            [
                'key' => 'teachers',
                'icon' => 'teachers',
                'title' => 'Qualified Teachers',
                'subtitle' => 'Experienced educators dedicated to every learner',
            ],
            [
                'key' => 'facilities',
                'icon' => 'facilities',
                'title' => 'Modern Facilities',
                'subtitle' => 'Classrooms and resources built for learning',
            ],
            [
                'key' => 'activities',
                'icon' => 'activities',
                'title' => 'Co-curricular Activities',
                'subtitle' => 'Sports, arts, and clubs that build character',
            ],
            [
                'key' => 'safety',
                'icon' => 'safety',
                'title' => 'Safe Environment',
                'subtitle' => 'A caring campus where students thrive',
            ],
        ];
    }

    /**
     * @return list<array{title: string, body: string}>
     */
    protected function defaultAdmissionsSteps(): array
    {
        return [
            [
                'title' => 'Enquire',
                'body' => 'Share your child’s details and the level you are applying for.',
            ],
            [
                'title' => 'Visit & assess',
                'body' => 'Meet our team, tour the campus, and complete any placement checks.',
            ],
            [
                'title' => 'Enrol',
                'body' => 'Confirm your place, complete registration, and prepare for term start.',
            ],
        ];
    }

    /**
     * @param  array<string, mixed>  $template
     * @return array<string, mixed>
     */
    protected function withSectionDefaults(array $template): array
    {
        $merged = array_merge([
            'about_headline' => 'About our school',
            'about_image' => '/images/marketing/hero-classroom.jpg',
            'admissions_title' => 'Admissions open',
            'admissions_body' => 'Join the {name} community. Start your application and take the next step for your child.',
            'admissions_steps' => $this->defaultAdmissionsSteps(),
            'contact_blurb' => 'We welcome families. Reach out with questions about programmes, fees, or visiting {name}.',
            'hero_image' => '/images/marketing/hero-students.jpg',
            'news_posts' => [],
        ], $template);

        $merged['hero_slides'] = $merged['hero_slides'] ?? $this->defaultHeroSlides($merged['hero_image']);
        $merged['news_posts'] = ! empty($merged['news_posts'])
            ? $merged['news_posts']
            : $this->defaultNewsPosts($merged);

        return $merged;
    }

    /**
     * @return list<array{image: string, alt: string}>
     */
    protected function defaultHeroSlides(?string $primaryImage = null): array
    {
        $primary = $primaryImage ?: '/images/marketing/hero-students.jpg';

        return [
            [
                'image' => $primary,
                'alt' => 'Students smiling in school uniforms',
            ],
            [
                'image' => '/images/marketing/hero-classroom.jpg',
                'alt' => 'Teacher supporting learners in class',
            ],
            [
                'image' => '/images/marketing/hero-primary.jpg',
                'alt' => 'Primary learners on campus',
            ],
        ];
    }

    /**
     * @param  array<string, mixed>  $template
     * @return list<array{title: string, slug: string, excerpt: string, body: string, image_url: string, days_ago: int}>
     */
    protected function defaultNewsPosts(array $template): array
    {
        $welcomeTitle = $template['news_title'] ?? 'Welcome to our new school website';
        $welcomeExcerpt = $template['news_excerpt'] ?? 'Explore programmes and admissions online.';
        $welcomeBody = $template['news_body'] ?? 'Browse our levels, read about the school, and begin an admissions enquiry.';

        return [
            [
                'title' => $welcomeTitle,
                'slug' => Str::slug($welcomeTitle),
                'excerpt' => $welcomeExcerpt,
                'body' => $welcomeBody,
                'image_url' => '/images/marketing/hero-students.jpg',
                'days_ago' => 1,
            ],
            [
                'title' => 'Open day for prospective families',
                'slug' => 'open-day-for-prospective-families',
                'excerpt' => 'Tour classrooms, meet teachers, and learn about our programmes.',
                'body' => 'Families are invited to visit {name} for a guided campus open day.',
                'image_url' => '/images/marketing/hero-classroom.jpg',
                'days_ago' => 4,
            ],
            [
                'title' => 'Literacy week celebrates young readers',
                'slug' => 'literacy-week-celebrates-young-readers',
                'excerpt' => 'Pupils shared stories, poetry, and reading challenges across grades.',
                'body' => '{name} celebrated literacy week with classroom showcases and library activities.',
                'image_url' => '/images/marketing/hero-primary.jpg',
                'days_ago' => 8,
            ],
            [
                'title' => 'Sports day builds teamwork and grit',
                'slug' => 'sports-day-builds-teamwork-and-grit',
                'excerpt' => 'House competitions highlighted fitness, friendship, and fair play.',
                'body' => 'Our annual sports day brought learners together in healthy competition at {name}.',
                'image_url' => '/images/marketing/hero-students.jpg',
                'days_ago' => 12,
            ],
            [
                'title' => 'STEM club showcases student projects',
                'slug' => 'stem-club-showcases-student-projects',
                'excerpt' => 'Robotics, science demos, and coding challenges filled the STEM hall.',
                'body' => 'Students at {name} presented inventive STEM projects to parents and peers.',
                'image_url' => '/images/marketing/hero-classroom.jpg',
                'days_ago' => 18,
            ],
            [
                'title' => 'Parent forum on wellbeing and learning',
                'slug' => 'parent-forum-on-wellbeing-and-learning',
                'excerpt' => 'Counselors and teachers shared practical tips for supporting learners at home.',
                'body' => '{name} hosted a parent forum focused on wellbeing, routines, and academic support.',
                'image_url' => '/images/marketing/hero-primary.jpg',
                'days_ago' => 24,
            ],
        ];
    }

    /**
     * @return array<string, array<string, mixed>>
     */
    protected function templates(): array
    {
        $features = $this->defaultFeatures();

        return [
            'nursery' => [
                'key' => 'nursery',
                'label' => 'Nursery School',
                'tagline' => 'A caring start for every little learner',
                'about' => '{name} is a nursery school in The Gambia focused on early childhood development, play-based learning, and a safe, nurturing environment for our youngest pupils.',
                'hero_eyebrow' => 'Early years education',
                'hero_headline' => 'Nurturing little learners with care and curiosity',
                'hero_body' => '{name} welcomes children into a warm early-years community focused on play, confidence, and first foundations.',
                'hero_image' => '/images/marketing/hero-primary.jpg',
                'cta_primary' => 'Apply for Admission',
                'cta_secondary' => 'Learn More',
                'levels_heading' => 'Our Academic Levels',
                'levels_intro' => 'Age-appropriate groups that help children grow in confidence at {name}.',
                'primary_color' => '#1A2B56',
                'accent_color' => '#1683F8',
                'features' => $features,
                'levels' => [
                    [
                        'name' => 'Baby Class',
                        'code' => 'baby',
                        'description' => 'Gentle care and sensory play for our youngest children.',
                        'image' => '/images/marketing/hero-primary.jpg',
                    ],
                    [
                        'name' => 'Toddler',
                        'code' => 'toddler',
                        'description' => 'Discovery, language, and social skills through guided play.',
                        'image' => '/images/marketing/hero-classroom.jpg',
                    ],
                    [
                        'name' => 'Reception',
                        'code' => 'reception',
                        'description' => 'School readiness with literacy, numeracy, and confidence.',
                        'image' => '/images/marketing/hero-students.jpg',
                    ],
                ],
                'news_title' => 'Welcome to our new school website',
                'news_excerpt' => 'Families can now learn about our nursery programmes and admissions online.',
                'news_body' => 'We are pleased to launch the public website for {name}. Explore our early years groups, learn about our approach, and apply for admission when you are ready.',
            ],
            'primary' => [
                'key' => 'primary',
                'label' => 'Primary School',
                'tagline' => 'Building strong foundations for lifelong learning',
                'about' => '{name} is a primary school serving Gambian learners with a focus on literacy, numeracy, character, and confidence in a supportive classroom community.',
                'hero_eyebrow' => 'Primary education',
                'hero_headline' => 'Building strong foundations for lifelong learning',
                'hero_body' => '{name} helps pupils grow in literacy, numeracy, and character in a supportive primary community.',
                'hero_image' => '/images/marketing/hero-primary.jpg',
                'cta_primary' => 'Apply for Admission',
                'cta_secondary' => 'Learn More',
                'levels_heading' => 'Our Academic Levels',
                'levels_intro' => 'Progressive grades that prepare pupils for secondary education at {name}.',
                'primary_color' => '#1A2B56',
                'accent_color' => '#1683F8',
                'features' => $features,
                'levels' => [
                    [
                        'name' => 'Grade 1',
                        'code' => 'g1',
                        'description' => 'First steps in literacy, numeracy, and classroom routines.',
                        'image' => '/images/marketing/hero-primary.jpg',
                    ],
                    [
                        'name' => 'Grade 2',
                        'code' => 'g2',
                        'description' => 'Growing confidence in reading, writing, and problem-solving.',
                        'image' => '/images/marketing/hero-classroom.jpg',
                    ],
                    [
                        'name' => 'Grade 3',
                        'code' => 'g3',
                        'description' => 'Deeper skills across core subjects with guided discovery.',
                        'image' => '/images/marketing/hero-students.jpg',
                    ],
                    [
                        'name' => 'Grade 4',
                        'code' => 'g4',
                        'description' => 'Independent learning habits and stronger academic focus.',
                        'image' => '/images/marketing/hero-primary.jpg',
                    ],
                    [
                        'name' => 'Grade 5',
                        'code' => 'g5',
                        'description' => 'Preparation for upper primary challenges and leadership.',
                        'image' => '/images/marketing/hero-classroom.jpg',
                    ],
                    [
                        'name' => 'Grade 6',
                        'code' => 'g6',
                        'description' => 'Ready for secondary pathways with solid foundations.',
                        'image' => '/images/marketing/hero-students.jpg',
                    ],
                ],
                'news_title' => 'Welcome to our new school website',
                'news_excerpt' => 'Discover our primary programmes, school life, and how to apply.',
                'news_body' => 'The public website for {name} is now live. Browse our primary grades, read about our school, and start an admissions enquiry online.',
            ],
            'basic_secondary' => [
                'key' => 'basic_secondary',
                'label' => 'Basic Secondary School',
                'tagline' => 'Guiding adolescents toward academic excellence',
                'about' => '{name} is a basic secondary school in The Gambia dedicated to strong academics, discipline, and pathways that prepare students for senior secondary and beyond.',
                'hero_eyebrow' => 'Basic secondary',
                'hero_headline' => 'Guiding adolescents toward academic excellence',
                'hero_body' => '{name} supports junior secondary learners with strong academics, discipline, and character.',
                'hero_image' => '/images/marketing/hero-students.jpg',
                'cta_primary' => 'Apply for Admission',
                'cta_secondary' => 'Learn More',
                'levels_heading' => 'Our Academic Levels',
                'levels_intro' => 'Form levels offered at {name} for junior secondary learners.',
                'primary_color' => '#1A2B56',
                'accent_color' => '#1683F8',
                'features' => $features,
                'levels' => [
                    [
                        'name' => 'Form 1',
                        'code' => 'f1',
                        'description' => 'A confident start to secondary learning and study habits.',
                        'image' => '/images/marketing/hero-classroom.jpg',
                    ],
                    [
                        'name' => 'Form 2',
                        'code' => 'f2',
                        'description' => 'Broader subject depth with co-curricular growth.',
                        'image' => '/images/marketing/hero-students.jpg',
                    ],
                    [
                        'name' => 'Form 3',
                        'code' => 'f3',
                        'description' => 'Exam readiness and pathways to senior secondary.',
                        'image' => '/images/marketing/hero-primary.jpg',
                    ],
                ],
                'news_title' => 'Welcome to our new school website',
                'news_excerpt' => 'Parents and students can explore our basic secondary offerings online.',
                'news_body' => "{name}'s public website is live. View our forms, learn about school life, and apply for admission through Gambia SchoolOS.",
            ],
            'senior_secondary' => [
                'key' => 'senior_secondary',
                'label' => 'Senior Secondary School',
                'tagline' => 'Preparing young leaders for exams and life beyond school',
                'about' => '{name} is a senior secondary school focused on examination readiness, subject streams, and personal growth for Gambian youth preparing for tertiary study and careers.',
                'hero_eyebrow' => 'Senior secondary',
                'hero_headline' => 'Preparing young leaders for life beyond school',
                'hero_body' => '{name} focuses on examination readiness, subject streams, and personal growth for senior learners.',
                'hero_image' => '/images/marketing/hero-students.jpg',
                'cta_primary' => 'Apply for Admission',
                'cta_secondary' => 'Learn More',
                'levels_heading' => 'Our Academic Levels',
                'levels_intro' => 'Senior secondary pathways available at {name}.',
                'primary_color' => '#1A2B56',
                'accent_color' => '#1683F8',
                'features' => $features,
                'levels' => [
                    [
                        'name' => 'Form 4',
                        'code' => 'f4',
                        'description' => 'Subject streams that open future academic pathways.',
                        'image' => '/images/marketing/hero-students.jpg',
                    ],
                    [
                        'name' => 'Form 5',
                        'code' => 'f5',
                        'description' => 'Focused exam preparation with mentoring support.',
                        'image' => '/images/marketing/hero-classroom.jpg',
                    ],
                    [
                        'name' => 'Form 6',
                        'code' => 'f6',
                        'description' => 'Advanced study preparing learners for tertiary life.',
                        'image' => '/images/marketing/hero-primary.jpg',
                    ],
                ],
                'news_title' => 'Welcome to our new school website',
                'news_excerpt' => 'Explore senior secondary streams and admissions on our new site.',
                'news_body' => 'Welcome to the new public website for {name}. Learn about our senior forms, school values, and how to apply for a place.',
            ],
            'combined' => [
                'key' => 'combined',
                'label' => 'All-through School',
                'tagline' => 'Knowledge • Character • A Brighter Tomorrow',
                'about' => '{name} is a Gambian school offering a continuum of learning from Nursery through Senior Secondary — nurturing knowledge, character, and a brighter tomorrow for every learner.',
                'about_headline' => 'A community built for every stage of learning',
                'about_image' => '/images/marketing/hero-classroom.jpg',
                'hero_eyebrow' => 'School community',
                'hero_headline' => "Nurturing Today's Learners for a Brighter Tomorrow",
                'hero_body' => 'From Nursery to Senior Secondary, {name} provides a caring, ambitious learning community where every student can thrive.',
                'hero_image' => '/images/marketing/hero-students.jpg',
                'hero_slides' => [
                    [
                        'image' => '/images/marketing/hero-students.jpg',
                        'alt' => 'Students smiling in school uniforms',
                    ],
                    [
                        'image' => '/images/marketing/hero-classroom.jpg',
                        'alt' => 'Teacher supporting learners in class',
                    ],
                    [
                        'image' => '/images/marketing/hero-primary.jpg',
                        'alt' => 'Primary learners on campus',
                    ],
                ],
                'cta_primary' => 'Apply for Admission',
                'cta_secondary' => 'Learn More',
                'levels_heading' => 'Our Academic Levels',
                'levels_intro' => 'From first steps in Nursery to Senior Secondary pathways, {name} supports learners with care, structure, and ambition.',
                'admissions_title' => 'Begin your journey with us',
                'admissions_body' => 'Families choose {name} for strong academics, character formation, and a welcoming campus. Start an admissions enquiry today.',
                'contact_blurb' => 'Visit us in Kanifing or send a message — the {name} team is ready to help with programmes, fees, and school visits.',
                'primary_color' => '#1A2B56',
                'accent_color' => '#1683F8',
                'features' => $features,
                'levels' => [
                    [
                        'name' => 'Nursery',
                        'code' => 'nursery',
                        'description' => 'A warm start with play-based learning and early foundations.',
                        'image' => '/images/marketing/hero-primary.jpg',
                    ],
                    [
                        'name' => 'Primary',
                        'code' => 'primary',
                        'description' => 'Strong literacy, numeracy, and character in supportive classes.',
                        'image' => '/images/marketing/hero-classroom.jpg',
                    ],
                    [
                        'name' => 'Basic Secondary',
                        'code' => 'basic_secondary',
                        'description' => 'Junior secondary academics with discipline and discovery.',
                        'image' => '/images/marketing/hero-students.jpg',
                    ],
                    [
                        'name' => 'Senior Secondary',
                        'code' => 'senior_secondary',
                        'description' => 'Exam pathways and leadership for life beyond school.',
                        'image' => '/images/marketing/hero-classroom.jpg',
                    ],
                ],
                'news_title' => 'Welcome to our new school website',
                'news_excerpt' => 'Our school landing page is live — explore programmes and apply online.',
                'news_body' => '{name} now has a dedicated public landing page on Gambia SchoolOS. Browse our levels, read about the school, and begin an admissions enquiry.',
            ],
        ];
    }

    /**
     * @param  list<array{name: string, code: string}>  $levels
     */
    protected function seedLevels(School $school, array $levels): void
    {
        foreach ($levels as $index => $level) {
            Level::query()->updateOrCreate(
                [
                    'school_id' => $school->id,
                    'code' => $level['code'],
                ],
                [
                    'name' => $level['name'],
                    'sort_order' => $index + 1,
                ]
            );
        }
    }

    /**
     * @param  array<string, mixed>  $template
     */
    protected function seedNewsPosts(School $school, array $template): void
    {
        $posts = $template['news_posts'] ?? $this->defaultNewsPosts($template);

        foreach ($posts as $post) {
            $slug = $post['slug'] ?? Str::slug($post['title']);

            NewsPost::query()->updateOrCreate(
                [
                    'school_id' => $school->id,
                    'slug' => $slug,
                ],
                [
                    'title' => $post['title'],
                    'excerpt' => $post['excerpt'] ?? null,
                    'body' => str_replace('{name}', $school->name, (string) ($post['body'] ?? '')),
                    'image_url' => $post['image_url'] ?? '/images/marketing/hero-students.jpg',
                    'published_at' => now()->subDays((int) ($post['days_ago'] ?? 1)),
                ]
            );
        }
    }
}
