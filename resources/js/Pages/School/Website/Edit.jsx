import InputError from '@/Components/InputError';
import InputLabel from '@/Components/InputLabel';
import PageHeader from '@/Components/SchoolOS/PageHeader';
import TextInput from '@/Components/TextInput';
import { useLocale } from '@/i18n/LocaleProvider';
import { mapEmbedUrl, mapQuery, parseCoordinates } from '@/lib/schoolMap';
import AppShell from '@/Layouts/AppShell';
import { Head, router, useForm } from '@inertiajs/react';
import { useState } from 'react';

const TABS = ['brand', 'about', 'academics', 'admissions', 'news', 'contact'];

function emptyLevelCard() {
    return { name: '', code: '', description: '', image: '/images/marketing/hero-students.jpg' };
}

function emptyFeature() {
    return { key: '', icon: 'teachers', title: '', subtitle: '' };
}

function emptyStep() {
    return { title: '', body: '' };
}

function emptyHeroSlide() {
    return { image: '/images/marketing/hero-students.jpg', alt: '' };
}

function FieldHint({ children }) {
    return <p className="mt-1 text-xs text-slate-500">{children}</p>;
}

export default function Edit({
    website = {},
    news = [],
    updateUrl,
    newsStoreUrl,
    publicUrl,
    nav = [],
    user,
    school,
}) {
    const { t } = useLocale();
    const [tab, setTab] = useState('brand');
    const [editingNewsId, setEditingNewsId] = useState(null);

    const form = useForm({
        tagline: website.tagline ?? '',
        about: website.about ?? '',
        phone: website.phone ?? '',
        email: website.email ?? '',
        address: website.address ?? '',
        region: website.region ?? '',
        primary_color: website.primary_color ?? '#1A2B56',
        accent_color: website.accent_color ?? '#1683F8',
        website_published: website.website_published ?? true,
        hero_headline: website.hero_headline ?? '',
        hero_body: website.hero_body ?? '',
        hero_image: website.hero_image ?? '',
        hero_slides: website.hero_slides?.length ? website.hero_slides : [emptyHeroSlide()],
        cta_primary: website.cta_primary ?? '',
        cta_secondary: website.cta_secondary ?? '',
        about_headline: website.about_headline ?? '',
        about_image: website.about_image ?? '',
        levels_heading: website.levels_heading ?? '',
        levels_intro: website.levels_intro ?? '',
        level_cards: website.level_cards?.length ? website.level_cards : [emptyLevelCard()],
        features: website.features?.length ? website.features : [emptyFeature()],
        admissions_title: website.admissions_title ?? '',
        admissions_body: website.admissions_body ?? '',
        admissions_steps: website.admissions_steps?.length ? website.admissions_steps : [emptyStep()],
        admissions_image: website.admissions_image ?? '',
        admissions_requirements: website.admissions_requirements ?? '',
        contact_blurb: website.contact_blurb ?? '',
        contact_hours: website.contact_hours ?? '',
        map_enabled: website.map_enabled ?? true,
        map_lat: website.map_lat ?? '',
        map_lng: website.map_lng ?? '',
    });

    const [mapPaste, setMapPaste] = useState('');
    const [mapNotice, setMapNotice] = useState(null); // 'parsed' | 'fail' | null

    function applyMapPaste() {
        const coords = parseCoordinates(mapPaste);
        if (coords) {
            form.setData((data) => ({ ...data, map_lat: coords.lat, map_lng: coords.lng }));
            setMapNotice('parsed');
            setMapPaste('');
        } else {
            setMapNotice('fail');
        }
    }

    const mapPreviewQuery = mapQuery({
        lat: form.data.map_lat,
        lng: form.data.map_lng,
        address: form.data.address,
        region: form.data.region,
        country: website.country,
    });

    const newsForm = useForm({
        title: '',
        excerpt: '',
        body: '',
        image_url: '',
        published: true,
        published_at: '',
    });

    function submit(e) {
        e.preventDefault();
        form.put(updateUrl, { preserveScroll: true });
    }

    function updateList(field, index, key, value) {
        const next = form.data[field].map((item, i) => (i === index ? { ...item, [key]: value } : item));
        form.setData(field, next);
    }

    function addListItem(field, factory) {
        form.setData(field, [...form.data[field], factory()]);
    }

    function removeListItem(field, index) {
        form.setData(
            field,
            form.data[field].filter((_, i) => i !== index),
        );
    }

    function submitNews(e) {
        e.preventDefault();
        if (editingNewsId) {
            newsForm.put(route('website.news.update', editingNewsId), {
                preserveScroll: true,
                onSuccess: () => {
                    newsForm.reset();
                    setEditingNewsId(null);
                },
            });
            return;
        }
        newsForm.post(newsStoreUrl, {
            preserveScroll: true,
            onSuccess: () => newsForm.reset('title', 'excerpt', 'body', 'image_url', 'published_at'),
        });
    }

    function startEditNews(post) {
        setEditingNewsId(post.id);
        newsForm.setData({
            title: post.title ?? '',
            excerpt: post.excerpt ?? '',
            body: post.body ?? '',
            image_url: post.image_url ?? '',
            published: Boolean(post.is_published),
            published_at: post.published_at ?? '',
        });
        setTab('news');
    }

    function moveHeroSlide(index, direction) {
        const next = [...form.data.hero_slides];
        const target = index + direction;
        if (target < 0 || target >= next.length) return;
        const tmp = next[index];
        next[index] = next[target];
        next[target] = tmp;
        form.setData('hero_slides', next);
    }

    function deleteNews(post) {
        if (!window.confirm(t('websiteAdmin.news.deleteConfirm'))) return;
        router.delete(route('website.news.destroy', post.id), { preserveScroll: true });
    }

    const tabLabel = {
        brand: t('websiteAdmin.tabs.brand'),
        about: t('websiteAdmin.tabs.about'),
        academics: t('websiteAdmin.tabs.academics'),
        admissions: t('websiteAdmin.tabs.admissions'),
        news: t('websiteAdmin.tabs.news'),
        contact: t('websiteAdmin.tabs.contact'),
    };

    return (
        <AppShell title={t('websiteAdmin.title')} nav={nav} user={user} school={school}>
            <Head title={t('websiteAdmin.title')} />

            <PageHeader
                title={t('websiteAdmin.title')}
                subtitle={t('websiteAdmin.subtitle')}
                actions={
                    <a href={publicUrl} target="_blank" rel="noreferrer" className="btn-outline !px-3 !py-2 text-xs">
                        {t('websiteAdmin.viewSite')}
                    </a>
                }
            />

            <div className="mb-4 flex flex-wrap gap-2">
                {TABS.map((key) => (
                    <button
                        key={key}
                        type="button"
                        onClick={() => setTab(key)}
                        className={`btn-cta rounded-lg px-3 py-2 text-xs ${
                            tab === key
                                ? 'bg-navy text-white'
                                : 'border border-slate-200 bg-white text-navy hover:bg-surface'
                        }`}
                    >
                        {tabLabel[key]}
                    </button>
                ))}
            </div>

            <form onSubmit={submit} className="space-y-5">
                {tab === 'brand' ? (
                    <section className="rounded-xl bg-white p-5 shadow-sm sm:p-6">
                        <h2 className="text-lg font-bold text-navy">{t('websiteAdmin.tabs.brand')}</h2>
                        <div className="mt-4 grid gap-4 sm:grid-cols-2">
                            <div className="sm:col-span-2">
                                <InputLabel htmlFor="tagline" value={t('websiteAdmin.fields.tagline')} />
                                <TextInput
                                    id="tagline"
                                    className="mt-1 w-full"
                                    value={form.data.tagline}
                                    onChange={(e) => form.setData('tagline', e.target.value)}
                                />
                                <InputError message={form.errors.tagline} className="mt-1" />
                            </div>
                            <div className="sm:col-span-2">
                                <InputLabel htmlFor="hero_headline" value={t('websiteAdmin.fields.heroHeadline')} />
                                <TextInput
                                    id="hero_headline"
                                    className="mt-1 w-full"
                                    value={form.data.hero_headline}
                                    onChange={(e) => form.setData('hero_headline', e.target.value)}
                                />
                            </div>
                            <div className="sm:col-span-2">
                                <InputLabel htmlFor="hero_body" value={t('websiteAdmin.fields.heroBody')} />
                                <textarea
                                    id="hero_body"
                                    rows={3}
                                    className="mt-1 w-full rounded-lg border-slate-200 text-sm"
                                    value={form.data.hero_body}
                                    onChange={(e) => form.setData('hero_body', e.target.value)}
                                />
                            </div>
                            <div className="sm:col-span-2">
                                <div className="flex items-center justify-between gap-3">
                                    <InputLabel value={t('websiteAdmin.fields.heroSlides')} />
                                    <button
                                        type="button"
                                        className="btn-outline !px-3 !py-2 text-xs"
                                        onClick={() => addListItem('hero_slides', emptyHeroSlide)}
                                    >
                                        {t('websiteAdmin.addSlide')}
                                    </button>
                                </div>
                                <FieldHint>{t('websiteAdmin.hints.heroSlides')}</FieldHint>
                                <div className="mt-3 space-y-3">
                                    {form.data.hero_slides.map((slide, index) => (
                                        <div key={`slide-${index}`} className="rounded-xl border border-slate-100 p-4">
                                            <div className="grid gap-3 sm:grid-cols-2">
                                                <div className="sm:col-span-2">
                                                    <InputLabel value={t('websiteAdmin.fields.slideImage')} />
                                                    <TextInput
                                                        className="mt-1 w-full"
                                                        value={slide.image}
                                                        onChange={(e) =>
                                                            updateList('hero_slides', index, 'image', e.target.value)
                                                        }
                                                    />
                                                </div>
                                                <div className="sm:col-span-2">
                                                    <InputLabel value={t('websiteAdmin.fields.slideAlt')} />
                                                    <TextInput
                                                        className="mt-1 w-full"
                                                        value={slide.alt}
                                                        onChange={(e) =>
                                                            updateList('hero_slides', index, 'alt', e.target.value)
                                                        }
                                                    />
                                                </div>
                                            </div>
                                            <div className="mt-3 flex flex-wrap gap-2">
                                                <button
                                                    type="button"
                                                    className="btn-outline !px-3 !py-1.5 text-xs"
                                                    onClick={() => moveHeroSlide(index, -1)}
                                                    disabled={index === 0}
                                                >
                                                    {t('websiteAdmin.moveUp')}
                                                </button>
                                                <button
                                                    type="button"
                                                    className="btn-outline !px-3 !py-1.5 text-xs"
                                                    onClick={() => moveHeroSlide(index, 1)}
                                                    disabled={index === form.data.hero_slides.length - 1}
                                                >
                                                    {t('websiteAdmin.moveDown')}
                                                </button>
                                                <button
                                                    type="button"
                                                    className="text-xs font-semibold text-red-600 hover:underline"
                                                    onClick={() => removeListItem('hero_slides', index)}
                                                >
                                                    {t('websiteAdmin.remove')}
                                                </button>
                                            </div>
                                        </div>
                                    ))}
                                </div>
                            </div>
                            <div className="grid grid-cols-2 gap-3">
                                <div>
                                    <InputLabel htmlFor="primary_color" value={t('websiteAdmin.fields.primaryColor')} />
                                    <TextInput
                                        id="primary_color"
                                        className="mt-1 w-full"
                                        value={form.data.primary_color}
                                        onChange={(e) => form.setData('primary_color', e.target.value)}
                                    />
                                </div>
                                <div>
                                    <InputLabel htmlFor="accent_color" value={t('websiteAdmin.fields.accentColor')} />
                                    <TextInput
                                        id="accent_color"
                                        className="mt-1 w-full"
                                        value={form.data.accent_color}
                                        onChange={(e) => form.setData('accent_color', e.target.value)}
                                    />
                                </div>
                            </div>
                            <div>
                                <InputLabel htmlFor="cta_primary" value={t('websiteAdmin.fields.ctaPrimary')} />
                                <TextInput
                                    id="cta_primary"
                                    className="mt-1 w-full"
                                    value={form.data.cta_primary}
                                    onChange={(e) => form.setData('cta_primary', e.target.value)}
                                />
                            </div>
                            <div>
                                <InputLabel htmlFor="cta_secondary" value={t('websiteAdmin.fields.ctaSecondary')} />
                                <TextInput
                                    id="cta_secondary"
                                    className="mt-1 w-full"
                                    value={form.data.cta_secondary}
                                    onChange={(e) => form.setData('cta_secondary', e.target.value)}
                                />
                            </div>
                            <label className="flex items-center gap-2 text-sm text-brand-ink sm:col-span-2">
                                <input
                                    type="checkbox"
                                    className="rounded border-slate-300 text-teal focus:ring-teal"
                                    checked={form.data.website_published}
                                    onChange={(e) => form.setData('website_published', e.target.checked)}
                                />
                                {t('websiteAdmin.fields.published')}
                            </label>
                        </div>
                    </section>
                ) : null}

                {tab === 'about' ? (
                    <section className="rounded-xl bg-white p-5 shadow-sm sm:p-6">
                        <h2 className="text-lg font-bold text-navy">{t('websiteAdmin.tabs.about')}</h2>
                        <div className="mt-4 space-y-4">
                            <div>
                                <InputLabel htmlFor="about_headline" value={t('websiteAdmin.fields.aboutHeadline')} />
                                <TextInput
                                    id="about_headline"
                                    className="mt-1 w-full"
                                    value={form.data.about_headline}
                                    onChange={(e) => form.setData('about_headline', e.target.value)}
                                />
                            </div>
                            <div>
                                <InputLabel htmlFor="about" value={t('websiteAdmin.fields.aboutBody')} />
                                <textarea
                                    id="about"
                                    rows={6}
                                    className="mt-1 w-full rounded-lg border-slate-200 text-sm"
                                    value={form.data.about}
                                    onChange={(e) => form.setData('about', e.target.value)}
                                />
                            </div>
                            <div>
                                <InputLabel htmlFor="about_image" value={t('websiteAdmin.fields.aboutImage')} />
                                <TextInput
                                    id="about_image"
                                    className="mt-1 w-full"
                                    value={form.data.about_image}
                                    onChange={(e) => form.setData('about_image', e.target.value)}
                                />
                            </div>
                        </div>
                    </section>
                ) : null}

                {tab === 'academics' ? (
                    <section className="space-y-5">
                        <div className="rounded-xl bg-white p-5 shadow-sm sm:p-6">
                            <h2 className="text-lg font-bold text-navy">{t('websiteAdmin.tabs.academics')}</h2>
                            <div className="mt-4 grid gap-4">
                                <div>
                                    <InputLabel htmlFor="levels_heading" value={t('websiteAdmin.fields.levelsHeading')} />
                                    <TextInput
                                        id="levels_heading"
                                        className="mt-1 w-full"
                                        value={form.data.levels_heading}
                                        onChange={(e) => form.setData('levels_heading', e.target.value)}
                                    />
                                </div>
                                <div>
                                    <InputLabel htmlFor="levels_intro" value={t('websiteAdmin.fields.levelsIntro')} />
                                    <textarea
                                        id="levels_intro"
                                        rows={3}
                                        className="mt-1 w-full rounded-lg border-slate-200 text-sm"
                                        value={form.data.levels_intro}
                                        onChange={(e) => form.setData('levels_intro', e.target.value)}
                                    />
                                </div>
                            </div>
                        </div>

                        <div className="rounded-xl bg-white p-5 shadow-sm sm:p-6">
                            <div className="flex items-center justify-between gap-3">
                                <h3 className="font-bold text-navy">{t('websiteAdmin.fields.levelCards')}</h3>
                                <button
                                    type="button"
                                    className="btn-outline !px-3 !py-2 text-xs"
                                    onClick={() => addListItem('level_cards', emptyLevelCard)}
                                >
                                    {t('websiteAdmin.addCard')}
                                </button>
                            </div>
                            <div className="mt-4 space-y-4">
                                {form.data.level_cards.map((card, index) => (
                                    <div key={`level-${index}`} className="rounded-xl border border-slate-100 p-4">
                                        <div className="grid gap-3 sm:grid-cols-2">
                                            <div>
                                                <InputLabel value={t('websiteAdmin.fields.cardName')} />
                                                <TextInput
                                                    className="mt-1 w-full"
                                                    value={card.name}
                                                    onChange={(e) => updateList('level_cards', index, 'name', e.target.value)}
                                                />
                                            </div>
                                            <div>
                                                <InputLabel value={t('websiteAdmin.fields.cardCode')} />
                                                <TextInput
                                                    className="mt-1 w-full"
                                                    value={card.code}
                                                    onChange={(e) => updateList('level_cards', index, 'code', e.target.value)}
                                                />
                                            </div>
                                            <div className="sm:col-span-2">
                                                <InputLabel value={t('websiteAdmin.fields.cardDescription')} />
                                                <textarea
                                                    rows={2}
                                                    className="mt-1 w-full rounded-lg border-slate-200 text-sm"
                                                    value={card.description}
                                                    onChange={(e) =>
                                                        updateList('level_cards', index, 'description', e.target.value)
                                                    }
                                                />
                                            </div>
                                            <div className="sm:col-span-2">
                                                <InputLabel value={t('websiteAdmin.fields.cardImage')} />
                                                <TextInput
                                                    className="mt-1 w-full"
                                                    value={card.image}
                                                    onChange={(e) => updateList('level_cards', index, 'image', e.target.value)}
                                                />
                                            </div>
                                        </div>
                                        <button
                                            type="button"
                                            className="mt-3 text-xs font-semibold text-red-600 hover:underline"
                                            onClick={() => removeListItem('level_cards', index)}
                                        >
                                            {t('websiteAdmin.remove')}
                                        </button>
                                    </div>
                                ))}
                            </div>
                        </div>

                        <div className="rounded-xl bg-white p-5 shadow-sm sm:p-6">
                            <div className="flex items-center justify-between gap-3">
                                <h3 className="font-bold text-navy">{t('websiteAdmin.fields.features')}</h3>
                                <button
                                    type="button"
                                    className="btn-outline !px-3 !py-2 text-xs"
                                    onClick={() => addListItem('features', emptyFeature)}
                                >
                                    {t('websiteAdmin.addFeature')}
                                </button>
                            </div>
                            <div className="mt-4 space-y-4">
                                {form.data.features.map((feature, index) => (
                                    <div key={`feature-${index}`} className="grid gap-3 rounded-xl border border-slate-100 p-4 sm:grid-cols-2">
                                        <div>
                                            <InputLabel value={t('websiteAdmin.fields.featureTitle')} />
                                            <TextInput
                                                className="mt-1 w-full"
                                                value={feature.title}
                                                onChange={(e) => updateList('features', index, 'title', e.target.value)}
                                            />
                                        </div>
                                        <div>
                                            <InputLabel value={t('websiteAdmin.fields.featureIcon')} />
                                            <TextInput
                                                className="mt-1 w-full"
                                                value={feature.icon}
                                                onChange={(e) => updateList('features', index, 'icon', e.target.value)}
                                            />
                                            <FieldHint>{t('websiteAdmin.hints.featureIcon')}</FieldHint>
                                        </div>
                                        <div className="sm:col-span-2">
                                            <InputLabel value={t('websiteAdmin.fields.featureSubtitle')} />
                                            <TextInput
                                                className="mt-1 w-full"
                                                value={feature.subtitle}
                                                onChange={(e) => updateList('features', index, 'subtitle', e.target.value)}
                                            />
                                        </div>
                                        <button
                                            type="button"
                                            className="text-start text-xs font-semibold text-red-600 hover:underline"
                                            onClick={() => removeListItem('features', index)}
                                        >
                                            {t('websiteAdmin.remove')}
                                        </button>
                                    </div>
                                ))}
                            </div>
                        </div>
                    </section>
                ) : null}

                {tab === 'admissions' ? (
                    <section className="rounded-xl bg-white p-5 shadow-sm sm:p-6">
                        <h2 className="text-lg font-bold text-navy">{t('websiteAdmin.tabs.admissions')}</h2>
                        <div className="mt-4 space-y-4">
                            <div>
                                <InputLabel htmlFor="admissions_title" value={t('websiteAdmin.fields.admissionsTitle')} />
                                <TextInput
                                    id="admissions_title"
                                    className="mt-1 w-full"
                                    value={form.data.admissions_title}
                                    onChange={(e) => form.setData('admissions_title', e.target.value)}
                                />
                            </div>
                            <div>
                                <InputLabel htmlFor="admissions_body" value={t('websiteAdmin.fields.admissionsBody')} />
                                <textarea
                                    id="admissions_body"
                                    rows={4}
                                    className="mt-1 w-full rounded-lg border-slate-200 text-sm"
                                    value={form.data.admissions_body}
                                    onChange={(e) => form.setData('admissions_body', e.target.value)}
                                />
                            </div>
                            <div>
                                <InputLabel htmlFor="admissions_image" value={t('websiteAdmin.fields.admissionsImage')} />
                                <TextInput
                                    id="admissions_image"
                                    dir="ltr"
                                    className="mt-1 w-full"
                                    placeholder="/images/marketing/hero-primary.jpg"
                                    value={form.data.admissions_image}
                                    onChange={(e) => form.setData('admissions_image', e.target.value)}
                                />
                                <FieldHint>{t('websiteAdmin.fields.admissionsImageHint')}</FieldHint>
                            </div>
                            <div>
                                <InputLabel
                                    htmlFor="admissions_requirements"
                                    value={t('websiteAdmin.fields.admissionsRequirements')}
                                />
                                <textarea
                                    id="admissions_requirements"
                                    rows={5}
                                    className="mt-1 w-full rounded-lg border-slate-200 text-sm"
                                    value={form.data.admissions_requirements}
                                    onChange={(e) => form.setData('admissions_requirements', e.target.value)}
                                />
                                <FieldHint>{t('websiteAdmin.fields.admissionsRequirementsHint')}</FieldHint>
                            </div>
                            <div className="flex items-center justify-between gap-3">
                                <h3 className="font-semibold text-navy">{t('websiteAdmin.fields.admissionsSteps')}</h3>
                                <button
                                    type="button"
                                    className="btn-outline !px-3 !py-2 text-xs"
                                    onClick={() => addListItem('admissions_steps', emptyStep)}
                                >
                                    {t('websiteAdmin.addStep')}
                                </button>
                            </div>
                            {form.data.admissions_steps.map((step, index) => (
                                <div key={`step-${index}`} className="rounded-xl border border-slate-100 p-4">
                                    <InputLabel value={t('websiteAdmin.fields.stepTitle')} />
                                    <TextInput
                                        className="mt-1 w-full"
                                        value={step.title}
                                        onChange={(e) => updateList('admissions_steps', index, 'title', e.target.value)}
                                    />
                                    <InputLabel className="mt-3" value={t('websiteAdmin.fields.stepBody')} />
                                    <textarea
                                        rows={2}
                                        className="mt-1 w-full rounded-lg border-slate-200 text-sm"
                                        value={step.body}
                                        onChange={(e) => updateList('admissions_steps', index, 'body', e.target.value)}
                                    />
                                    <button
                                        type="button"
                                        className="mt-3 text-xs font-semibold text-red-600 hover:underline"
                                        onClick={() => removeListItem('admissions_steps', index)}
                                    >
                                        {t('websiteAdmin.remove')}
                                    </button>
                                </div>
                            ))}
                        </div>
                    </section>
                ) : null}

                {tab === 'contact' ? (
                    <section className="rounded-xl bg-white p-5 shadow-sm sm:p-6">
                        <h2 className="text-lg font-bold text-navy">{t('websiteAdmin.tabs.contact')}</h2>
                        <div className="mt-4 grid gap-4 sm:grid-cols-2">
                            <div className="sm:col-span-2">
                                <InputLabel htmlFor="contact_blurb" value={t('websiteAdmin.fields.contactBlurb')} />
                                <textarea
                                    id="contact_blurb"
                                    rows={3}
                                    className="mt-1 w-full rounded-lg border-slate-200 text-sm"
                                    value={form.data.contact_blurb}
                                    onChange={(e) => form.setData('contact_blurb', e.target.value)}
                                />
                            </div>
                            <div>
                                <InputLabel htmlFor="phone" value={t('websiteAdmin.fields.phone')} />
                                <TextInput
                                    id="phone"
                                    className="mt-1 w-full"
                                    value={form.data.phone}
                                    onChange={(e) => form.setData('phone', e.target.value)}
                                />
                            </div>
                            <div>
                                <InputLabel htmlFor="email" value={t('websiteAdmin.fields.email')} />
                                <TextInput
                                    id="email"
                                    type="email"
                                    className="mt-1 w-full"
                                    value={form.data.email}
                                    onChange={(e) => form.setData('email', e.target.value)}
                                />
                            </div>
                            <div>
                                <InputLabel htmlFor="address" value={t('websiteAdmin.fields.address')} />
                                <TextInput
                                    id="address"
                                    className="mt-1 w-full"
                                    value={form.data.address}
                                    onChange={(e) => form.setData('address', e.target.value)}
                                />
                            </div>
                            <div>
                                <InputLabel htmlFor="region" value={t('websiteAdmin.fields.region')} />
                                <TextInput
                                    id="region"
                                    className="mt-1 w-full"
                                    value={form.data.region}
                                    onChange={(e) => form.setData('region', e.target.value)}
                                />
                            </div>
                            <div className="sm:col-span-2">
                                <InputLabel htmlFor="contact_hours" value={t('websiteAdmin.fields.contactHours')} />
                                <textarea
                                    id="contact_hours"
                                    rows={3}
                                    className="mt-1 w-full rounded-lg border-slate-200 text-sm"
                                    placeholder={t('websiteAdmin.fields.contactHoursExample')}
                                    value={form.data.contact_hours}
                                    onChange={(e) => form.setData('contact_hours', e.target.value)}
                                />
                                <InputError message={form.errors.contact_hours} className="mt-1" />
                                <FieldHint>{t('websiteAdmin.map.inbox')}</FieldHint>
                            </div>

                            <div className="rounded-xl border border-slate-200 p-4 sm:col-span-2">
                                <div className="flex flex-wrap items-center justify-between gap-3">
                                    <h3 className="text-sm font-bold text-navy">{t('websiteAdmin.map.title')}</h3>
                                    <label className="flex items-center gap-2 text-sm text-slate-600">
                                        <input
                                            type="checkbox"
                                            className="rounded border-slate-300 text-brand-bright focus:ring-brand-bright"
                                            checked={!!form.data.map_enabled}
                                            onChange={(e) => form.setData('map_enabled', e.target.checked)}
                                        />
                                        {t('websiteAdmin.map.show')}
                                    </label>
                                </div>
                                <FieldHint>{t('websiteAdmin.map.help')}</FieldHint>

                                {form.data.map_enabled ? (
                                    <div className="mt-4 grid gap-4 lg:grid-cols-2">
                                        <div className="space-y-3">
                                            <div>
                                                <InputLabel htmlFor="map_paste" value={t('websiteAdmin.map.paste')} />
                                                <div className="mt-1 flex gap-2">
                                                    <TextInput
                                                        id="map_paste"
                                                        dir="ltr"
                                                        className="w-full"
                                                        value={mapPaste}
                                                        onChange={(e) => {
                                                            setMapPaste(e.target.value);
                                                            setMapNotice(null);
                                                        }}
                                                        onKeyDown={(e) => {
                                                            if (e.key === 'Enter') {
                                                                e.preventDefault();
                                                                applyMapPaste();
                                                            }
                                                        }}
                                                    />
                                                    <button type="button" className="btn-navy" onClick={applyMapPaste}>
                                                        {t('websiteAdmin.map.apply')}
                                                    </button>
                                                </div>
                                                {mapNotice === 'parsed' ? (
                                                    <p className="mt-1 text-xs font-medium text-brand-green">
                                                        {t('websiteAdmin.map.parsed')}
                                                    </p>
                                                ) : null}
                                                {mapNotice === 'fail' ? (
                                                    <p className="mt-1 text-xs font-medium text-red-600">
                                                        {t('websiteAdmin.map.parseFail')}
                                                    </p>
                                                ) : null}
                                            </div>
                                            <div className="grid grid-cols-2 gap-3">
                                                <div>
                                                    <InputLabel htmlFor="map_lat" value={t('websiteAdmin.map.lat')} />
                                                    <TextInput
                                                        id="map_lat"
                                                        dir="ltr"
                                                        inputMode="decimal"
                                                        className="mt-1 w-full"
                                                        value={form.data.map_lat}
                                                        onChange={(e) => form.setData('map_lat', e.target.value)}
                                                    />
                                                    <InputError message={form.errors.map_lat} className="mt-1" />
                                                </div>
                                                <div>
                                                    <InputLabel htmlFor="map_lng" value={t('websiteAdmin.map.lng')} />
                                                    <TextInput
                                                        id="map_lng"
                                                        dir="ltr"
                                                        inputMode="decimal"
                                                        className="mt-1 w-full"
                                                        value={form.data.map_lng}
                                                        onChange={(e) => form.setData('map_lng', e.target.value)}
                                                    />
                                                    <InputError message={form.errors.map_lng} className="mt-1" />
                                                </div>
                                            </div>
                                            {form.data.map_lat !== '' || form.data.map_lng !== '' ? (
                                                <button
                                                    type="button"
                                                    className="btn-outline"
                                                    onClick={() => {
                                                        form.setData((data) => ({ ...data, map_lat: '', map_lng: '' }));
                                                        setMapNotice(null);
                                                    }}
                                                >
                                                    {t('websiteAdmin.map.clear')}
                                                </button>
                                            ) : null}
                                        </div>

                                        <div>
                                            <p className="text-xs font-bold uppercase tracking-wide text-slate-500">
                                                {t('websiteAdmin.map.preview')}
                                            </p>
                                            <div className="mt-1 overflow-hidden rounded-xl border border-slate-200 bg-slate-50">
                                                {mapPreviewQuery ? (
                                                    <iframe
                                                        key={mapPreviewQuery}
                                                        title={t('websiteAdmin.map.preview')}
                                                        src={mapEmbedUrl(mapPreviewQuery, 'en')}
                                                        loading="lazy"
                                                        className="block h-56 w-full border-0"
                                                    />
                                                ) : (
                                                    <div className="flex h-56 items-center justify-center px-4 text-center text-sm text-slate-500">
                                                        {t('websiteAdmin.map.noLocation')}
                                                    </div>
                                                )}
                                            </div>
                                        </div>
                                    </div>
                                ) : null}
                            </div>
                        </div>
                    </section>
                ) : null}

                {tab !== 'news' ? (
                    <div className="flex flex-wrap items-center gap-3">
                        <button type="submit" className="btn-teal" disabled={form.processing}>
                            {t('websiteAdmin.save')}
                        </button>
                        {form.recentlySuccessful ? (
                            <span className="text-sm font-medium text-brand-green">{t('websiteAdmin.saved')}</span>
                        ) : null}
                    </div>
                ) : null}
            </form>

            {tab === 'news' ? (
                <div className="space-y-5">
                    <section className="rounded-xl bg-white p-5 shadow-sm sm:p-6">
                        <h2 className="text-lg font-bold text-navy">
                            {editingNewsId ? t('websiteAdmin.news.edit') : t('websiteAdmin.news.create')}
                        </h2>
                        <form onSubmit={submitNews} className="mt-4 space-y-4">
                            <div>
                                <InputLabel htmlFor="news_title" value={t('websiteAdmin.news.title')} />
                                <TextInput
                                    id="news_title"
                                    className="mt-1 w-full"
                                    value={newsForm.data.title}
                                    onChange={(e) => newsForm.setData('title', e.target.value)}
                                    required
                                />
                                <InputError message={newsForm.errors.title} className="mt-1" />
                            </div>
                            <div>
                                <InputLabel htmlFor="news_excerpt" value={t('websiteAdmin.news.excerpt')} />
                                <textarea
                                    id="news_excerpt"
                                    rows={2}
                                    className="mt-1 w-full rounded-lg border-slate-200 text-sm"
                                    value={newsForm.data.excerpt}
                                    onChange={(e) => newsForm.setData('excerpt', e.target.value)}
                                />
                            </div>
                            <div>
                                <InputLabel htmlFor="news_body" value={t('websiteAdmin.news.body')} />
                                <textarea
                                    id="news_body"
                                    rows={5}
                                    className="mt-1 w-full rounded-lg border-slate-200 text-sm"
                                    value={newsForm.data.body}
                                    onChange={(e) => newsForm.setData('body', e.target.value)}
                                />
                            </div>
                            <div>
                                <InputLabel htmlFor="news_image" value={t('websiteAdmin.news.image')} />
                                <TextInput
                                    id="news_image"
                                    className="mt-1 w-full"
                                    value={newsForm.data.image_url}
                                    onChange={(e) => newsForm.setData('image_url', e.target.value)}
                                />
                                <FieldHint>{t('websiteAdmin.hints.imageUrl')}</FieldHint>
                            </div>
                            <div className="grid gap-4 sm:grid-cols-2">
                                <div>
                                    <InputLabel htmlFor="news_published_at" value={t('websiteAdmin.news.publishedAt')} />
                                    <TextInput
                                        id="news_published_at"
                                        type="date"
                                        className="mt-1 w-full"
                                        value={newsForm.data.published_at}
                                        onChange={(e) => newsForm.setData('published_at', e.target.value)}
                                    />
                                </div>
                                <label className="flex items-end gap-2 pb-2 text-sm text-brand-ink">
                                    <input
                                        type="checkbox"
                                        className="rounded border-slate-300 text-teal focus:ring-teal"
                                        checked={newsForm.data.published}
                                        onChange={(e) => newsForm.setData('published', e.target.checked)}
                                    />
                                    {t('websiteAdmin.news.published')}
                                </label>
                            </div>
                            <div className="flex flex-wrap gap-2">
                                <button type="submit" className="btn-teal" disabled={newsForm.processing}>
                                    {editingNewsId ? t('websiteAdmin.news.update') : t('websiteAdmin.news.create')}
                                </button>
                                {editingNewsId ? (
                                    <button
                                        type="button"
                                        className="btn-outline"
                                        onClick={() => {
                                            setEditingNewsId(null);
                                            newsForm.reset();
                                        }}
                                    >
                                        {t('websiteAdmin.news.cancel')}
                                    </button>
                                ) : null}
                            </div>
                        </form>
                    </section>

                    <section className="rounded-xl bg-white p-5 shadow-sm sm:p-6">
                        <h3 className="font-bold text-navy">{t('websiteAdmin.news.list')}</h3>
                        {news.length === 0 ? (
                            <p className="mt-3 text-sm text-slate-500">{t('websiteAdmin.news.empty')}</p>
                        ) : (
                            <ul className="mt-4 divide-y divide-slate-100">
                                {news.map((post) => (
                                    <li key={post.id} className="flex flex-col gap-3 py-4 sm:flex-row sm:items-start sm:justify-between">
                                        <div className="min-w-0">
                                            <p className="font-semibold text-navy">{post.title}</p>
                                            <p className="mt-1 text-xs text-slate-400">
                                                {post.is_published
                                                    ? t('websiteAdmin.news.statusPublished')
                                                    : t('websiteAdmin.news.statusDraft')}
                                                {post.published_at ? ` · ${post.published_at}` : ''}
                                            </p>
                                            {post.excerpt ? (
                                                <p className="mt-2 line-clamp-2 text-sm text-slate-500">{post.excerpt}</p>
                                            ) : null}
                                        </div>
                                        <div className="flex shrink-0 gap-2">
                                            <button
                                                type="button"
                                                className="btn-outline !px-3 !py-2 text-xs"
                                                onClick={() => startEditNews(post)}
                                            >
                                                {t('websiteAdmin.news.edit')}
                                            </button>
                                            <button
                                                type="button"
                                                className="btn-outline !border-red-200 !px-3 !py-2 text-xs !text-red-600"
                                                onClick={() => deleteNews(post)}
                                            >
                                                {t('websiteAdmin.remove')}
                                            </button>
                                        </div>
                                    </li>
                                ))}
                            </ul>
                        )}
                    </section>
                </div>
            ) : null}
        </AppShell>
    );
}
