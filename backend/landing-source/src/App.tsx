import React, { useState, useEffect, useRef, useCallback, useMemo } from "react";
import { motion } from "motion/react";

// Import modular premium subcomponents
import GlobalHUD from "./components/GlobalHUD";
import HeroScene from "./components/HeroScene";
import AttendanceScene from "./components/AttendanceScene";
import LeaveScene from "./components/LeaveScene";
import PayrollScene from "./components/PayrollScene";
import EmployeeScene from "./components/EmployeeScene";
import AnalyticsScene from "./components/AnalyticsScene";
import PricingScene from "./components/PricingScene";
import QaScene from "./components/QaScene";
import TestimonialScene from "./components/TestimonialScene";
import TrustScene from "./components/TrustScene";
import HowItWorksScene from "./components/HowItWorksScene";
import DemoScene from "./components/DemoScene";
import FinaleScene from "./components/FinaleScene";
import OnboardingModal from "./components/OnboardingModal";

function readBootstrapData(): Record<string, any> {
  if (typeof window === 'undefined') return {};
  const node = document.getElementById('landing-app-data');
  if (!node) return {};
  try {
    return JSON.parse(node.textContent || '{}');
  } catch { return {}; }
}

export default function App() {
  const [currentSlide, setCurrentSlide] = useState(0);
  const [onboardingOpen, setOnboardingOpen] = useState(false);
  const bootstrap = useMemo(() => readBootstrapData(), []);
  const packages = useMemo(() => Array.isArray(bootstrap?.packages) ? bootstrap.packages : [], [bootstrap]);
  const loginUrl = String(bootstrap?.loginUrl || '/login');
  const turnstileEnabled = Boolean(bootstrap?.turnstileEnabled);
  const turnstileSiteKey = String(bootstrap?.turnstileSiteKey || '');
  const turnstileHideTestNotice = Boolean(bootstrap?.turnstileHideTestNotice);
  const hasActiveTrialPackages = Boolean(bootstrap?.hasActiveTrialPackages);

  // Auto-open onboarding from URL params (?openOnboarding=1&package=xxx&startMode=trial)
  const [requestedPackageUuid, setRequestedPackageUuid] = useState('');
  const [requestedStartMode, setRequestedStartMode] = useState('');

  useEffect(() => {
    if (typeof window === 'undefined') return;
    const params = new URLSearchParams(window.location.search);
    if (params.get('openOnboarding') === '1') {
      const pkg = String(params.get('package') || '').trim();
      const mode = String(params.get('startMode') || '').trim();
      setRequestedPackageUuid(pkg);
      setRequestedStartMode(mode === 'pending_payment' ? 'pending_payment' : 'trial');
      setOnboardingOpen(true);
    }
  }, []);
  const totalSlides = 13;
  const isCooldownRef = useRef(false);
  const touchStartRef = useRef<number | null>(null);

  const slideTitles = [
    "BERANDA",
    "ABSENSI",
    "CUTI",
    "PAYROLL",
    "EMPLOYEE",
    "ANALYTICS",
    "PAKET",
    "Q&A",
    "TESTIMONIAL",
    "KEPATUHAN",
    "CARA PAKAI",
    "DEMO",
    "CTA PENUTUP"
  ];

  const [isMobile, setIsMobile] = useState(false);

  useEffect(() => {
    const checkMobile = () => {
      // iPad Mini (768px - 1024px) and other mid-size tablets should fall back to vertical view layout for comfortable reading
      setIsMobile(window.innerWidth < 1120);
    };
    checkMobile();
    window.addEventListener("resize", checkMobile);
    return () => window.removeEventListener("resize", checkMobile);
  }, []);

  const nextSlide = () => {
    if (isMobile) return;
    if (isCooldownRef.current) return;
    setCurrentSlide((prev) => {
      const nextIndex = Math.min(prev + 1, totalSlides - 1);
      if (nextIndex !== prev) {
        triggerCooldown();
      }
      return nextIndex;
    });
  };

  const prevSlide = () => {
    if (isMobile) return;
    if (isCooldownRef.current) return;
    setCurrentSlide((prev) => {
      const prevIndex = Math.max(prev - 1, 0);
      if (prevIndex !== prev) {
        triggerCooldown();
      }
      return prevIndex;
    });
  };

  const navigateToSlide = (index: number) => {
    if (isMobile) {
      setCurrentSlide(index);
      const element = document.getElementById(`scene-0${index + 1}`);
      if (element) {
        element.scrollIntoView({ behavior: "smooth", block: "start" });
      }
    } else {
      if (isCooldownRef.current || index === currentSlide) return;
      triggerCooldown();
      setCurrentSlide(index);
    }
  };

  const triggerCooldown = () => {
    isCooldownRef.current = true;
    setTimeout(() => {
      isCooldownRef.current = false;
    }, 600);
  };

  const openOnboarding = useCallback(() => {
    console.log('openOnboarding CALLED');
    setOnboardingOpen(true);
  }, []);

  // Keyboard navigation & debounced wheel listeners
  useEffect(() => {
    if (isMobile) return;

    const handleKeyDown = (e: KeyboardEvent) => {
      if (onboardingOpen) return; // Don't navigate when modal is open
      if (e.key === "ArrowRight" || e.key === "ArrowDown" || e.key === " ") {
        e.preventDefault();
        nextSlide();
      } else if (e.key === "ArrowLeft" || e.key === "ArrowUp") {
        e.preventDefault();
        prevSlide();
      }
    };

    const handleWheel = (e: WheelEvent) => {
      if (onboardingOpen) return; // Don't capture scroll when modal is open
      e.preventDefault();
      if (isCooldownRef.current) return;

      // Filter micro-scrolls from trackpad
      const threshold = 35;
      if (Math.abs(e.deltaY) > threshold) {
        if (e.deltaY > 0) {
          nextSlide();
        } else {
          prevSlide();
        }
      } else if (Math.abs(e.deltaX) > threshold) {
        if (e.deltaX > 0) {
          nextSlide();
        } else {
          prevSlide();
        }
      }
    };

    // Attach listeners on windows
    window.addEventListener("keydown", handleKeyDown, { passive: false });
    window.addEventListener("wheel", handleWheel, { passive: false });

    return () => {
      window.removeEventListener("keydown", handleKeyDown);
      window.removeEventListener("wheel", handleWheel);
    };
  }, [currentSlide, isMobile, onboardingOpen]);

  // Track active section during native scroll on mobile
  useEffect(() => {
    if (!isMobile) return;

    let ticking = false;

    const handleMobileScroll = () => {
      if (!ticking) {
        window.requestAnimationFrame(() => {
          const elements = Array.from({ length: totalSlides }).map((_, idx) => 
            document.getElementById(`scene-0${idx + 1}`)
          );

          let activeIndex = 0;
          let minDistance = Infinity;

          elements.forEach((el, idx) => {
            if (!el) return;
            const rect = el.getBoundingClientRect();
            const distance = Math.abs(rect.top);
            if (distance < minDistance) {
              minDistance = distance;
              activeIndex = idx;
            }
          });

          if (activeIndex !== currentSlide) {
            setCurrentSlide(activeIndex);
          }
          ticking = false;
        });
        ticking = true;
      }
    };

    window.addEventListener("scroll", handleMobileScroll, { passive: true });
    return () => window.removeEventListener("scroll", handleMobileScroll);
  }, [isMobile, currentSlide]);

  // Touch handlers for responsive swiping on mobile devices (only in desktop slider mode)
  const handleTouchStart = (e: React.TouchEvent) => {
    if (isMobile) return;
    if (e.touches.length > 0) {
      touchStartRef.current = e.touches[0].clientX;
    }
  };

  const handleTouchEnd = (e: React.TouchEvent) => {
    if (isMobile) return;
    if (touchStartRef.current === null) return;
    
    const touchEnd = e.changedTouches[0].clientX;
    const diff = touchStartRef.current - touchEnd;
    const swipeThreshold = 60; // minimum diff to register swiping

    if (Math.abs(diff) > swipeThreshold) {
      if (diff > 0) {
        nextSlide();
      } else {
        prevSlide();
      }
    }
    touchStartRef.current = null;
  };

  return (
    <div 
      className={isMobile ? "w-full min-h-screen overflow-x-hidden overflow-y-visible bg-[#FAFAF8] text-[#1A1D24] relative flex flex-col items-start justify-start" : "w-screen h-screen overflow-hidden bg-[#FAFAF8] text-[#1A1D24] relative flex flex-col items-start justify-start select-none"}
      onTouchStart={isMobile ? undefined : handleTouchStart}
      onTouchEnd={isMobile ? undefined : handleTouchEnd}
      id="app-root-container"
    >
      {/* 
        LAYER 01 — FIXED PARALLAX BACKGROUND
        Background tetap diam, content slide di atasnya — efek parallax nyata.
      */}
      {/* LAYER 01 — BASE: Animated wallpaper (fixed, always visible) */}
      <div className="fixed inset-0 pointer-events-none z-0 overflow-hidden">
        <div className="absolute inset-0 bg-gradient-to-br from-[#FFF5ED] via-[#FAFAF8] to-[#F0F4FF] animate-gradient-shift" />
        <div className="absolute inset-0 tech-grid opacity-[0.06]" />
        
        {/* Large colored orbs breathing slowly */}
        <div className="absolute top-[5%] left-[5%] w-[700px] h-[700px] rounded-full bg-gradient-to-br from-[#FF6600]/15 via-orange-300/10 to-transparent blur-[140px] animate-breathing" style={{ animationDuration: '8s' }} />
        <div className="absolute bottom-[10%] right-[8%] w-[600px] h-[600px] rounded-full bg-gradient-to-br from-blue-400/10 via-purple-300/5 to-transparent blur-[130px] animate-breathing" style={{ animationDuration: '10s', animationDelay: '2s' }} />
        <div className="absolute top-[50%] left-[50%] w-[500px] h-[500px] -translate-x-1/2 -translate-y-1/2 rounded-full bg-gradient-to-br from-amber-200/12 via-orange-200/8 to-transparent blur-[120px] animate-breathing" style={{ animationDuration: '6s', animationDelay: '1s' }} />
        
        {/* Floating particles */}
        <div className="absolute top-[20%] left-[12%] w-2 h-2 rounded-full bg-[#FF6600]/25 animate-particle" />
        <div className="absolute top-[35%] left-[55%] w-1.5 h-1.5 rounded-full bg-orange-400/25 animate-particle-delayed" />
        <div className="absolute top-[65%] left-[25%] w-2.5 h-2.5 rounded-full bg-amber-300/20 animate-particle" style={{ animationDuration: '24s' }} />
        <div className="absolute top-[15%] left-[75%] w-1.5 h-1.5 rounded-full bg-blue-400/20 animate-particle-delayed" style={{ animationDuration: '20s' }} />
        <div className="absolute top-[80%] left-[80%] w-2 h-2 rounded-full bg-orange-300/20 animate-particle" style={{ animationDuration: '28s' }} />
        <div className="absolute top-[45%] left-[88%] w-1.5 h-1.5 rounded-full bg-[#FF6600]/20 animate-particle-delayed" style={{ animationDuration: '16s' }} />
        <div className="absolute top-[5%] left-[45%] w-1.5 h-1.5 rounded-full bg-amber-200/25 animate-particle" style={{ animationDuration: '26s' }} />
        
        {/* Light beam */}
        <div className="absolute -top-[30%] -right-[5%] w-[500px] h-[800px] bg-gradient-to-b from-[#FF6600]/6 via-orange-200/4 to-transparent blur-[100px] rotate-[35deg] animate-breathing" style={{ animationDuration: '6s' }} />
      </div>

      {/* LAYER 02 — PARALLAX: Slow-moving decorative shapes (15vw/slide) */}
      <motion.div 
        className="fixed inset-0 w-[200vw] h-full pointer-events-none z-0"
        animate={{ x: isMobile ? '0vw' : `-${currentSlide * 15}vw` }}
        transition={{ type: "spring", stiffness: 120, damping: 22 }}
      >
        <div className="absolute top-[20%] left-[15%] w-[200px] h-[200px] border-2 border-[#FF6600]/10 rounded-full animate-float" />
        <div className="absolute bottom-[25%] left-[35%] w-[150px] h-[150px] border-2 border-orange-300/10 rotate-45 animate-float-delayed" />
        <div className="absolute top-[40%] left-[60%] w-[120px] h-[120px] border-2 border-blue-300/10 rounded-full animate-float" style={{ animationDuration: '14s' }} />
        <div className="absolute top-[70%] left-[8%] w-[100px] h-[100px] border-2 border-amber-300/10 -rotate-12 animate-float-delayed" />
        <div className="absolute top-[10%] left-[40%] w-[80px] h-[80px] bg-[#FF6600]/5 rotate-[30deg] animate-float" style={{ animationDuration: '16s' }} />
      </motion.div>

      {/* LAYER 03 — PARALLAX: Fast-moving accent blobs (40vw/slide) */}
      <motion.div 
        className="fixed inset-0 w-[300vw] h-full pointer-events-none z-0"
        animate={{ x: isMobile ? '0vw' : `-${currentSlide * 40}vw` }}
        transition={{ type: "spring", stiffness: 120, damping: 22 }}
      >
        <div className="absolute top-[15%] right-[20%] w-[120px] h-[120px] bg-gradient-to-br from-[#FF6600]/8 to-transparent rounded-full blur-sm animate-breathing" style={{ animationDuration: '5s' }} />
        <div className="absolute bottom-[30%] left-[25%] w-[90px] h-[90px] bg-gradient-to-br from-blue-400/6 to-transparent rounded-full blur-sm animate-breathing" style={{ animationDuration: '6s', animationDelay: '2s' }} />
        <div className="absolute top-[55%] right-[35%] w-[70px] h-[70px] bg-gradient-to-br from-amber-300/8 to-transparent rotate-12 animate-breathing" style={{ animationDuration: '7s', animationDelay: '1s' }} />
        <div className="absolute bottom-[15%] right-[15%] w-[60px] h-[60px] bg-gradient-to-br from-orange-400/6 to-transparent rounded-full blur-sm animate-breathing" style={{ animationDuration: '4s', animationDelay: '3s' }} />
      </motion.div>

      {/* Global Interactive Heads Up Display HUD (Header/Footer controls) */}
      <GlobalHUD 
        currentSlide={currentSlide}
        totalSlides={totalSlides}
        slideTitles={slideTitles}
        onNavigate={navigateToSlide}
        onNext={nextSlide}
        onPrev={prevSlide}
        isMobile={isMobile}
        loginUrl={loginUrl}
      />

      {/* 
        MASTER CAROUSEL STAGE CONTAINER
        Slides left and right with comfortable physical spring physics (momentum)
      */}
      {isMobile ? (
        <div className="flex flex-col w-full h-auto z-10 gap-24 py-20 px-4 pb-28" id="scrolling-main-mobile">
          {/* SCENE 01 — BERANDA */}
          <section className="w-full h-auto py-4" id="scene-01">
            <HeroScene onNavigate={navigateToSlide} packages={packages} onOpenOnboarding={openOnboarding} />
          </section>

          {/* SCENE 02 — ABSENSI */}
          <section className="w-full h-auto py-4" id="scene-02">
            <AttendanceScene />
          </section>

          {/* SCENE 03 — CUTI */}
          <section className="w-full h-auto py-4" id="scene-03">
            <LeaveScene />
          </section>

          {/* SCENE 04 — PAYROLL */}
          <section className="w-full h-auto py-4" id="scene-04">
            <PayrollScene />
          </section>

          {/* SCENE 05 — EMPLOYEE */}
          <section className="w-full h-auto py-4" id="scene-05">
            <EmployeeScene />
          </section>

          {/* SCENE 06 — ANALYTICS */}
          <section className="w-full h-auto py-4" id="scene-06">
            <AnalyticsScene />
          </section>

          {/* SCENE 07 — PAKET */}
          <section className="w-full h-auto py-4" id="scene-07">
            <PricingScene packages={packages} onOpenOnboarding={openOnboarding} turnstileEnabled={turnstileEnabled} turnstileSiteKey={turnstileSiteKey} turnstileHideTestNotice={turnstileHideTestNotice} hasActiveTrialPackages={hasActiveTrialPackages} />
          </section>

          {/* SCENE 08 — Q&A */}
          <section className="w-full h-auto py-4" id="scene-08">
            <QaScene isMobile={isMobile} />
          </section>

          {/* SCENE 09 — TESTIMONIAL */}
          <section className="w-full h-auto py-4" id="scene-09">
            <TestimonialScene isMobile={isMobile} />
          </section>

          {/* SCENE 10 — KEPATUHAN */}
          <section className="w-full h-auto py-4" id="scene-10">
            <TrustScene />
          </section>

          {/* SCENE 11 — CARA PAKAI */}
          <section className="w-full h-auto py-4" id="scene-11">
            <HowItWorksScene />
          </section>

          {/* SCENE 12 — DEMO */}
          <section className="w-full h-auto py-4" id="scene-12">
            <DemoScene />
          </section>

          {/* SCENE 13 — CTA PENUTUP */}
          <section className="w-full h-auto py-4" id="scene-13">
            <FinaleScene onNavigate={navigateToSlide} isMobile={isMobile} onOpenOnboarding={openOnboarding} />
          </section>
        </div>
      ) : (
        <motion.main 
          className="flex w-[1300vw] h-full z-10 force-gpu"
          animate={{ x: isMobile ? "0vw" : `-${currentSlide * 100}vw` }}
          transition={{ type: "spring", stiffness: 120, damping: 22 }}
        >
          {/* SCENE 01 — BERANDA */}
          <section className="w-[100vw] h-full flex-shrink-0" id="scene-01">
            <HeroScene onNavigate={navigateToSlide} packages={packages} onOpenOnboarding={openOnboarding} />
          </section>

          {/* SCENE 02 — ABSENSI */}
          <section className="w-[100vw] h-full flex-shrink-0" id="scene-02">
            <AttendanceScene />
          </section>

          {/* SCENE 03 — CUTI */}
          <section className="w-[100vw] h-full flex-shrink-0" id="scene-03">
            <LeaveScene />
          </section>

          {/* SCENE 04 — PAYROLL */}
          <section className="w-[100vw] h-full flex-shrink-0" id="scene-04">
            <PayrollScene />
          </section>

          {/* SCENE 05 — EMPLOYEE */}
          <section className="w-[100vw] h-full flex-shrink-0" id="scene-05">
            <EmployeeScene />
          </section>

          {/* SCENE 06 — ANALYTICS */}
          <section className="w-[100vw] h-full flex-shrink-0" id="scene-06">
            <AnalyticsScene />
          </section>

          {/* SCENE 07 — PAKET */}
          <section className="w-[100vw] h-full flex-shrink-0" id="scene-07">
            <PricingScene packages={packages} onOpenOnboarding={openOnboarding} turnstileEnabled={turnstileEnabled} turnstileSiteKey={turnstileSiteKey} turnstileHideTestNotice={turnstileHideTestNotice} hasActiveTrialPackages={hasActiveTrialPackages} />
          </section>

          {/* SCENE 08 — Q&A */}
          <section className="w-[100vw] h-full flex-shrink-0" id="scene-08">
            <QaScene isMobile={isMobile} />
          </section>

          {/* SCENE 09 — TESTIMONIAL */}
          <section className="w-[100vw] h-full flex-shrink-0" id="scene-09">
            <TestimonialScene isMobile={isMobile} />
          </section>

          {/* SCENE 10 — KEPATUHAN */}
          <section className="w-[100vw] h-full flex-shrink-0" id="scene-10">
            <TrustScene />
          </section>

          {/* SCENE 11 — CARA PAKAI */}
          <section className="w-[100vw] h-full flex-shrink-0" id="scene-11">
            <HowItWorksScene />
          </section>

          {/* SCENE 12 — DEMO */}
          <section className="w-[100vw] h-full flex-shrink-0" id="scene-12">
            <DemoScene />
          </section>

          {/* SCENE 13 — CTA PENUTUP */}
          <section className="w-[100vw] h-full flex-shrink-0" id="scene-13">
            <FinaleScene onNavigate={navigateToSlide} isMobile={isMobile} onOpenOnboarding={openOnboarding} />
          </section>
        </motion.main>
      )}
      {/* Onboarding Modal */}
      {onboardingOpen && (
        <OnboardingModal
          packages={packages}
          onClose={() => setOnboardingOpen(false)}
          loginUrl={loginUrl}
          turnstileEnabled={turnstileEnabled}
          turnstileSiteKey={turnstileSiteKey}
          turnstileHideTestNotice={turnstileHideTestNotice}
          requestedPackageUuid={requestedPackageUuid}
          requestedStartMode={requestedStartMode}
          hasActiveTrialPackages={hasActiveTrialPackages}
        />
      )}
    </div>
  );
}
