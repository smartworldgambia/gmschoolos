import FeaturesGrid from '@/Components/Marketing/FeaturesGrid';
import FinalCTA from '@/Components/Marketing/FinalCTA';
import Footer from '@/Components/Marketing/Footer';
import HeroSection from '@/Components/Marketing/HeroSection';
import Navbar from '@/Components/Marketing/Navbar';
import PricingSection from '@/Components/Marketing/PricingSection';
import ProductPreview from '@/Components/Marketing/ProductPreview';
import StatisticsSection from '@/Components/Marketing/StatisticsSection';
import TestimonialSection from '@/Components/Marketing/TestimonialSection';
import { scrollToSection } from '@/Components/Marketing/scrollToSection';
import { Head } from '@inertiajs/react';
import { useEffect } from 'react';

export default function Landing({
    prices = [],
    trialDays = 30,
    stats = null,
    statsMode = 'live',
    illustrativeStats = [],
    registerUrl = '/register-school',
    loginUrl = '/login',
    demoUrl = '#contact',
    marketing = {},
    seo = {},
}) {
    const title = seo.title ?? 'Gambia SchoolOS — School Management & Learning';
    const description =
        seo.description ??
        'Cloud-based school management and LMS for Gambian schools — administration, teaching, exams, fees and more.';

    // Re-align hash targets after layout settles (hero height/images), offset by sticky navbar.
    useEffect(() => {
        const run = () => {
            const hash = window.location.hash?.slice(1);
            if (hash) scrollToSection(hash, { behavior: 'instant' });
        };

        run();
        const raf = window.requestAnimationFrame(run);
        const t1 = window.setTimeout(run, 50);
        const t2 = window.setTimeout(run, 200);
        const t3 = window.setTimeout(run, 500);
        window.addEventListener('hashchange', run);
        window.addEventListener('load', run);

        return () => {
            window.cancelAnimationFrame(raf);
            window.clearTimeout(t1);
            window.clearTimeout(t2);
            window.clearTimeout(t3);
            window.removeEventListener('hashchange', run);
            window.removeEventListener('load', run);
        };
    }, []);

    return (
        <>
            <Head title={title}>
                <meta head-key="description" name="description" content={description} />
                <meta head-key="og:title" property="og:title" content={title} />
                <meta head-key="og:description" property="og:description" content={description} />
                <meta head-key="og:type" property="og:type" content="website" />
                {seo.image ? <meta head-key="og:image" property="og:image" content={seo.image} /> : null}
                <meta head-key="twitter:card" name="twitter:card" content="summary_large_image" />
            </Head>

            <div id="top" className="min-h-screen bg-surface">
                <Navbar loginUrl={loginUrl} registerUrl={registerUrl} />

                <main>
                    <HeroSection
                        registerUrl={registerUrl}
                        demoUrl={demoUrl}
                        heroSlides={marketing.hero_slides ?? []}
                    />

                    <StatisticsSection
                        mode={statsMode}
                        stats={illustrativeStats}
                        live={stats}
                    />

                    <FeaturesGrid />

                    <ProductPreview />

                    <PricingSection prices={prices} registerUrl={registerUrl} trialDays={trialDays} />

                    <TestimonialSection testimonial={marketing.testimonial ?? null} />

                    <FinalCTA registerUrl={registerUrl} demoUrl={demoUrl} />
                </main>

                <Footer
                    contact={{
                        email: marketing.contact_email,
                        phone: marketing.phone,
                        phone_secondary: marketing.phone_secondary,
                        address: marketing.address,
                        website: marketing.website,
                        support_email: marketing.support_email,
                    }}
                    social={marketing.social ?? {}}
                    tagline={marketing.tagline}
                />
            </div>
        </>
    );
}
