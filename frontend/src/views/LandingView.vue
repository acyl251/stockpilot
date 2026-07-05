<template>
  <div class="landing" @scroll.passive="onScroll">

    <!-- ══════════════════════════════ MODAL DEMO ══════════════════════════════ -->
    <Transition name="modal">
      <div v-if="showModal" class="modal-overlay" @click.self="closeModal">
        <div class="modal-box">
          <button class="modal-close" @click="closeModal">✕</button>

          <div v-if="!submitted">
            <div class="modal-header">
              <div class="logo-icon sm">S</div>
              <div>
                <h2>Demander un accès</h2>
                <p>Nous vous contacterons sous 24h pour créer votre compte.</p>
              </div>
            </div>

            <div v-if="formError" class="form-error">{{ formError }}</div>

            <form @submit.prevent="submitDemo" class="demo-form">
              <div class="form-row">
                <div class="form-group">
                  <label>Prénom *</label>
                  <input v-model="form.prenom" type="text" placeholder="" required/>
                </div>
                <div class="form-group">
                  <label>Nom *</label>
                  <input v-model="form.nom" type="text" placeholder="" required/>
                </div>
              </div>
              <div class="form-row">
                <div class="form-group">
                  <label>Email professionnel *</label>
                  <input v-model="form.email" type="email" placeholder="vous@societe.com" required/>
                </div>
                <div class="form-group">
                  <label>Téléphone</label>
                  <input v-model="form.telephone" type="tel" placeholder="+216 XX XXX XXX"/>
                </div>
              </div>
              <div class="form-row">
                <div class="form-group">
                  <label>Nom de la société *</label>
                  <input v-model="form.societe" type="text" placeholder="Mon Entreprise SARL" required/>
                </div>
                <div class="form-group">
                  <label>Secteur d'activité</label>
                  <input v-model="form.secteur" type="text" placeholder="Commerce, Industrie…"/>
                </div>
              </div>
              <div class="form-group">
                <label>Plan souhaité</label>
                <div class="plan-select">
                  <button type="button"
                    v-for="[slug, label] in [['starter','Starter'],['essentiel','Essentiel'],['pro','Pro'],['entreprise','Entreprise']]"
                    :key="slug"
                    :class="['plan-opt', { active: form.plan_souhaite === slug }]"
                    @click="form.plan_souhaite = slug">
                    {{ label }}
                  </button>
                </div>
              </div>
              <div class="form-group">
                <label>Message (optionnel)</label>
                <textarea v-model="form.message" rows="3" placeholder="Décrivez brièvement vos besoins…"></textarea>
              </div>
              <button type="submit" class="submit-btn" :disabled="submitting">
                <span v-if="submitting">Envoi en cours…</span>
                <span v-else>Envoyer ma demande →</span>
              </button>
            </form>
          </div>

          <!-- Succès -->
          <div v-else class="modal-success">
            <div class="success-icon">✓</div>
            <h2>Demande envoyée !</h2>
            <p>Nous avons bien reçu votre demande. Notre équipe vous contactera sous <strong>24 heures</strong> pour finaliser votre accès à StockPilot.</p>
            <button class="submit-btn" @click="closeModal">Fermer</button>
          </div>
        </div>
      </div>
    </Transition>

    <!-- ══════════════════════════════ NAV ══════════════════════════════ -->
    <nav class="nav" :class="{ scrolled: scrolled }">
      <div class="nav-inner">
        <div class="nav-logo">
          <div class="logo-icon">S</div>
          <span class="logo-text">StockPilot</span>
        </div>
        <div class="nav-links">
          <a href="#features">Fonctionnalités</a>
          <a href="#stats">Chiffres</a>
          <a href="#plans">Plans</a>
        </div>
        <router-link to="/login" class="nav-cta">Se connecter</router-link>
      </div>
    </nav>

    <!-- ══════════════════════════════ HERO ══════════════════════════════ -->
    <section class="hero" ref="heroRef">
      <!-- Parallax layers -->
      <div class="layer layer-bg"     :style="{ transform: `translateY(${parallax * 0.5}px)` }"></div>
      <div class="layer layer-grid"   :style="{ transform: `translateY(${parallax * 0.3}px)` }"></div>
      <div class="layer layer-glow1"  :style="{ transform: `translateY(${parallax * 0.4}px) translateX(-50%)` }"></div>
      <div class="layer layer-glow2"  :style="{ transform: `translateY(${parallax * 0.6}px) translateX(-50%)` }"></div>
      <div class="layer layer-dots"   :style="{ transform: `translateY(${parallax * 0.2}px)` }"></div>

      <!-- Floating cards parallax -->
      <div class="float-card card-a" :style="{ transform: `translateY(${parallax * 0.15}px) rotate(-6deg)` }">
        <div class="fc-label">CA ce mois</div>
        <div class="fc-value">47 832 DT</div>
        <div class="fc-trend">↑ +12%</div>
      </div>
      <div class="float-card card-b" :style="{ transform: `translateY(${parallax * 0.25}px) rotate(5deg)` }">
        <div class="fc-label">En stock</div>
        <div class="fc-value">284</div>
        <div class="fc-sub">produits actifs</div>
      </div>
      <div class="float-card card-c" :style="{ transform: `translateY(${parallax * 0.1}px) rotate(-3deg)` }">
        <div class="fc-icon">🤖</div>
        <div class="fc-label">IA activée</div>
        <div class="fc-sub">Prévisions 30j</div>
      </div>

      <!-- Hero content -->
      <div class="hero-content" :style="{ transform: `translateY(${parallax * 0.1}px)` }">
        <div class="hero-badge">
          <span class="badge-dot"></span>
          Plateforme SaaS Multi-tenant
        </div>
        <h1 class="hero-title">
          Gérez votre stock<br/>
          <span class="gradient-text">avec intelligence</span>
        </h1>
        <p class="hero-desc">
          StockPilot unifie votre catalogue, vos mouvements et vos analyses dans une
          seule plateforme. Powered by IA — disponible pour toutes les tailles d'entreprises.
        </p>
        <div class="hero-actions">
          <router-link to="/login" class="btn-primary">
            Accéder à la plateforme
            <svg width="16" height="16" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2.5"><path d="M5 12h14M12 5l7 7-7 7"/></svg>
          </router-link>
          <a href="#features" class="btn-ghost">Découvrir →</a>
        </div>
        <div class="hero-badges">
          <span>✓ Multi-tenant isolé</span>
          <span>✓ IA intégrée</span>
          <span>✓ Temps réel</span>
        </div>
      </div>

      <div class="hero-scroll" :style="{ opacity: scrollOpacity }">
        <div class="scroll-arrow"></div>
        <span>Défiler</span>
      </div>
    </section>


    <!-- ══════════════════════════════ FEATURES ══════════════════════════════ -->
    <section class="features" id="features">
      <div class="section-inner">
        <div class="section-tag">Fonctionnalités</div>
        <h2 class="section-title">Tout ce dont vous avez besoin</h2>
        <p class="section-sub">Une suite complète pour piloter votre stock du catalogue à l'analyse prédictive.</p>

        <div class="features-grid">
          <div class="feat-card feat-main">
            <div class="feat-icon-wrap" style="background:#1e3a5f">
              <svg width="28" height="28" fill="none" stroke="#60a5fa" stroke-width="2" viewBox="0 0 24 24"><rect x="3" y="3" width="7" height="7"/><rect x="14" y="3" width="7" height="7"/><rect x="14" y="14" width="7" height="7"/><rect x="3" y="14" width="7" height="7"/></svg>
            </div>
            <h3>Tableau de bord temps réel</h3>
            <p>KPIs en direct : CA mensuel, valeur du stock, produits en rupture, mouvements du jour. Graphiques sur 7 jours.</p>
            <div class="feat-visual">
              <div class="mini-kpi"><span>47 832</span><label>CA DT</label></div>
              <div class="mini-kpi red"><span>7</span><label>Ruptures</label></div>
              <div class="mini-kpi green"><span>284</span><label>Actifs</label></div>
            </div>
          </div>

          <div class="feat-card">
            <div class="feat-icon-wrap" style="background:#1a3a2a">
              <svg width="28" height="28" fill="none" stroke="#34d399" stroke-width="2" viewBox="0 0 24 24"><path d="M3 3h18v18H3z"/><path d="M9 9h6v6H9z"/></svg>
            </div>
            <h3>Catalogue intelligent</h3>
            <p>Référencement complet des produits avec catégories colorées, types personnalisés et prix TTC calculés automatiquement.</p>
          </div>

          <div class="feat-card">
            <div class="feat-icon-wrap" style="background:#2a1a3a">
              <svg width="28" height="28" fill="none" stroke="#a78bfa" stroke-width="2" viewBox="0 0 24 24"><path d="M12 2L2 7l10 5 10-5-10-5z"/><path d="M2 17l10 5 10-5"/><path d="M2 12l10 5 10-5"/></svg>
            </div>
            <h3>Mouvements tracés</h3>
            <p>Chaque entrée et sortie enregistrée avec l'identité de l'auteur. Transaction atomique garantissant la cohérence du stock.</p>
          </div>

          <div class="feat-card feat-ai">
            <div class="feat-icon-wrap" style="background:#3a2a1a">
              <svg width="28" height="28" fill="none" stroke="#f59e0b" stroke-width="2" viewBox="0 0 24 24"><circle cx="12" cy="12" r="3"/><path d="M12 1v4M12 19v4M4.22 4.22l2.83 2.83M16.95 16.95l2.83 2.83M1 12h4M19 12h4M4.22 19.78l2.83-2.83M16.95 7.05l2.83-2.83"/></svg>
            </div>
            <div class="ai-badge">IA</div>
            <h3>Intelligence Artificielle</h3>
            <p>StockPilot analyse votre historique de ventes et vous dit <strong style="color:#f1f5f9">quand commander</strong>, <strong style="color:#f1f5f9">combien commander</strong>, et vous alerte avant qu'un produit tombe en rupture.</p>
          </div>

          <div class="feat-card">
            <div class="feat-icon-wrap" style="background:#1a2a3a">
              <svg width="28" height="28" fill="none" stroke="#38bdf8" stroke-width="2" viewBox="0 0 24 24"><path d="M17 21v-2a4 4 0 0 0-4-4H5a4 4 0 0 0-4 4v2"/><circle cx="9" cy="7" r="4"/><path d="M23 21v-2a4 4 0 0 0-3-3.87"/><path d="M16 3.13a4 4 0 0 1 0 7.75"/></svg>
            </div>
            <h3>Multi-tenant sécurisé</h3>
            <p>Isolation totale entre organisations via TenantScope Eloquent. Chaque société voit uniquement ses propres données.</p>
          </div>

          <div class="feat-card">
            <div class="feat-icon-wrap" style="background:#1a2e1a">
              <svg width="28" height="28" fill="none" stroke="#86efac" stroke-width="2" viewBox="0 0 24 24"><rect x="3" y="11" width="18" height="11" rx="2"/><path d="M7 11V7a5 5 0 0 1 10 0v4"/></svg>
            </div>
            <h3>Rôles et permissions</h3>
            <p>Quatre rôles distincts : Super Admin, Admin, Gestionnaire et Opérateur. Chaque action est contrôlée par middleware.</p>
          </div>
        </div>
      </div>
    </section>

    <!-- ══════════════════════════════ STATS ══════════════════════════════ -->
    <section class="stats" id="stats">
      <div class="stats-bg"></div>
      <div class="section-inner stats-inner">
        <div class="section-tag light">La plateforme en chiffres</div>
        <h2 class="section-title light">Conçue pour performer</h2>
        <div class="stats-grid">
          <div class="stat-card" v-for="s in statsList" :key="s.label">
            <div class="stat-number">{{ s.value }}</div>
            <div class="stat-label">{{ s.label }}</div>
            <div class="stat-desc">{{ s.desc }}</div>
          </div>
        </div>
      </div>
    </section>

    <!-- ══════════════════════════════ HOW ══════════════════════════════ -->
    <section class="how">
      <div class="section-inner">
        <div class="section-tag">Comment ça marche</div>
        <h2 class="section-title">Opérationnel en 3 étapes</h2>
        <div class="steps-grid">
          <div class="step" v-for="(step, i) in steps" :key="i">
            <div class="step-num">{{ String(i + 1).padStart(2, '0') }}</div>
            <div class="step-line" v-if="i < steps.length - 1"></div>
            <div class="step-icon">{{ step.icon }}</div>
            <h3>{{ step.title }}</h3>
            <p>{{ step.desc }}</p>
          </div>
        </div>
      </div>
    </section>

    <!-- ══════════════════════════════ PLANS ══════════════════════════════ -->
    <section class="plans" id="plans">
      <div class="section-inner">
        <div class="section-tag">Tarification</div>
        <h2 class="section-title">Choisissez votre plan</h2>
        <p class="section-sub">Adapté à toutes les tailles d'entreprises.</p>
        <!-- Skeleton pendant le chargement -->
        <div v-if="!plansLoaded" class="plans-grid">
          <div v-for="i in 4" :key="i" class="plan-card" style="opacity:.4">
            <div style="background:rgba(255,255,255,.1);height:16px;border-radius:4px;width:60%;margin-bottom:16px"></div>
            <div style="background:rgba(255,255,255,.1);height:40px;border-radius:4px;width:40%;margin-bottom:20px"></div>
            <div v-for="j in 5" :key="j" style="background:rgba(255,255,255,.07);height:12px;border-radius:4px;margin-bottom:10px"></div>
          </div>
        </div>

        <div v-else class="plans-grid">
          <div class="plan-card" v-for="plan in plansList" :key="plan.id"
            :class="{ featured: plan.nom.toLowerCase() === 'pro' }">
            <div class="plan-badge" v-if="plan.nom.toLowerCase() === 'pro'">Populaire</div>
            <div class="plan-name">{{ plan.nom }}</div>
            <div class="plan-price">
              <template v-if="plan.prix_mensuel === 0">
                <span class="price-amount">Gratuit</span>
              </template>
              <template v-else>
                <div class="price-annual-row">
                  <span class="price-amount">{{ plan.prix_mensuel * 12 }}</span>
                  <span class="price-unit">DT/an</span>
                </div>
                <div class="price-monthly">{{ plan.prix_mensuel }} DT/mois</div>
              </template>
            </div>
            <ul class="plan-features">
              <li v-for="f in (planFeatures[plan.nom.toLowerCase()] ?? [])" :key="f">
                <svg width="14" height="14" fill="none" stroke="currentColor" stroke-width="2.5" viewBox="0 0 24 24"><path d="M20 6L9 17l-5-5"/></svg>
                {{ f }}
              </li>
              <li v-if="plan.ia_activee">
                <svg width="14" height="14" fill="none" stroke="#f59e0b" stroke-width="2.5" viewBox="0 0 24 24"><path d="M20 6L9 17l-5-5"/></svg>
                <span style="color:#f59e0b">IA activée</span>
              </li>
            </ul>
            <button type="button" class="plan-btn"
              :class="{ 'plan-btn-primary': plan.nom.toLowerCase() === 'pro' }"
              @click="openModal(plan.nom.toLowerCase())">
              {{ plan.prix_mensuel === 0 ? 'Commencer gratuitement' : 'Commencer' }}
            </button>
          </div>
        </div>
      </div>
    </section>

    <!-- ══════════════════════════════ CTA ══════════════════════════════ -->
    <section class="cta-section">
      <div class="cta-bg"></div>
      <div class="cta-inner">
        <div class="cta-glow"></div>
        <h2>Prêt à prendre le contrôle<br/>de votre stock ?</h2>
        <p>Rejoignez StockPilot et transformez votre gestion de stock en avantage compétitif.</p>
        <button type="button" class="btn-primary btn-large" @click="openModal()">
          Demander un accès
          <svg width="18" height="18" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2.5"><path d="M5 12h14M12 5l7 7-7 7"/></svg>
        </button>
      </div>
    </section>

    <!-- ══════════════════════════════ FOOTER ══════════════════════════════ -->
    <footer class="footer">
      <div class="footer-inner">
        <div class="footer-logo">
          <div class="logo-icon sm">S</div>
          <span>StockPilot</span>
        </div>
        <p class="footer-copy">Projet académique · ISET Sousse · 2025/2026 · Acyl Dhifallah &amp; Med Alaa Edine Boufares</p>
        <router-link to="/login" class="footer-link">Se connecter →</router-link>
      </div>
    </footer>

  </div>
</template>

<script setup lang="ts">
import { ref, computed, onMounted, onUnmounted } from 'vue'
import { publicApi } from '@/services/api'

// ─── Parallax / scroll ────────────────────────────────────────────────────────
const scrolled  = ref(false)
const parallax  = ref(0)
const scrollOpacity = computed(() => Math.max(0, 1 - parallax.value * 0.005))

function onScroll() {
  const y = window.scrollY
  scrolled.value = y > 60
  parallax.value = y
}

onMounted(async () => {
  window.addEventListener('scroll', onScroll, { passive: true })
  try {
    const { data } = await publicApi.getPlans()
    plansList.value = data
  } catch {
    // fallback silencieux si API indisponible
  } finally {
    plansLoaded.value = true
  }
})
onUnmounted(() => window.removeEventListener('scroll', onScroll))

// ─── Modal demande démo ───────────────────────────────────────────────────────
const showModal  = ref(false)
const submitted  = ref(false)
const submitting = ref(false)
const formError  = ref('')

const form = ref({
  prenom: '', nom: '', email: '', telephone: '',
  societe: '', secteur: '', plan_souhaite: 'pro', message: '',
})

function openModal(plan = 'pro') {
  form.value.plan_souhaite = plan
  submitted.value = false
  formError.value = ''
  showModal.value = true
  document.body.style.overflow = 'hidden'
}

function closeModal() {
  showModal.value = false
  document.body.style.overflow = ''
}

async function submitDemo() {
  submitting.value = true
  formError.value  = ''
  try {
    await publicApi.sendDemoRequest(form.value)
    submitted.value = true
  } catch (e: any) {
    const errors = e.response?.data?.errors
    if (errors) {
      formError.value = Object.values(errors).flat().join(' — ')
    } else {
      formError.value = e.response?.data?.message ?? 'Une erreur est survenue.'
    }
  } finally {
    submitting.value = false
  }
}

const statsList = [
  { value: '100%', label: 'Isolation des données',    desc: 'Multi-tenant garanti par TenantScope' },
  { value: '5',    label: 'Fonctions IA',              desc: 'Prévisions, anomalies, KPIs prédictifs' },
  { value: '4',    label: 'Plans disponibles',         desc: 'Starter · Essentiel · Pro · Entreprise' },
  { value: '<1s',  label: 'Temps de réponse API',      desc: 'Grâce au cache Laravel et Eloquent' },
]

const steps = [
  { icon: '🏢', title: 'Créer votre société',    desc: 'Le super admin crée votre organisation et votre compte admin en quelques secondes.' },
  { icon: '📦', title: 'Configurer votre stock', desc: 'Définissez vos catégories, types de produits et référencez votre catalogue.' },
  { icon: '📊', title: 'Piloter en temps réel',  desc: 'Enregistrez vos mouvements et laissez l\'IA optimiser vos réapprovisionnements.' },
]

const plansList   = ref<any[]>([])
const plansLoaded = ref(false)

const planFeatures: Record<string, string[]> = {
  starter:    ['1 utilisateur', '30 produits', '5 tables', '100 ventes/mois', 'Support email'],
  essentiel:  ['2 utilisateurs', '200 produits', '10 tables', '1 000 ventes/mois', 'Factures PDF légales', 'Alertes WhatsApp', 'Support email'],
  pro:        ['5 utilisateurs', '500 produits', '30 tables', '5 000 ventes/mois', 'Module restauration', 'QR code menu digital', 'Dashboard avancé', 'Support prioritaire'],
  entreprise: ['Utilisateurs illimités', 'Produits illimités', 'Tables illimitées', 'Ventes illimitées', 'Multi-points de vente', 'Accompagnement & formation', 'Support dédié'],
}
</script>

<style scoped>
/* ═══════════════ BASE ═══════════════ */
.landing {
  background: #030712;
  color: #f1f5f9;
  font-family: 'Inter', system-ui, sans-serif;
  overflow-x: hidden;
}

/* ═══════════════ NAV ═══════════════ */
.nav {
  position: fixed; top: 0; left: 0; right: 0; z-index: 100;
  transition: all .3s ease;
  padding: 20px 0;
}
.nav.scrolled {
  background: rgba(3,7,18,.85);
  backdrop-filter: blur(16px);
  border-bottom: 1px solid rgba(255,255,255,.06);
  padding: 12px 0;
}
.nav-inner {
  max-width: 1200px; margin: 0 auto;
  padding: 0 32px;
  display: flex; align-items: center; justify-content: space-between;
}
.nav-logo { display: flex; align-items: center; gap: 10px; }
.logo-icon {
  width: 36px; height: 36px; background: #f59e0b; border-radius: 8px;
  display: flex; align-items: center; justify-content: center;
  font-size: 18px; font-weight: 800; color: #fff;
}
.logo-icon.sm { width: 28px; height: 28px; font-size: 14px; border-radius: 6px; }
.logo-text { font-size: 18px; font-weight: 700; color: #fff; }
.nav-links { display: flex; gap: 32px; }
.nav-links a { color: #94a3b8; font-size: 14px; text-decoration: none; transition: color .2s; }
.nav-links a:hover { color: #fff; }
.nav-cta {
  background: #f59e0b; color: #0f172a;
  padding: 8px 20px; border-radius: 8px;
  font-size: 14px; font-weight: 700; text-decoration: none;
  transition: opacity .2s;
}
.nav-cta:hover { opacity: .85; }

/* ═══════════════ HERO ═══════════════ */
.hero {
  position: relative; min-height: 100vh;
  display: flex; align-items: center; justify-content: center;
  overflow: hidden;
}

/* Parallax layers */
.layer { position: absolute; inset: 0; will-change: transform; pointer-events: none; }

.layer-bg {
  background: radial-gradient(ellipse 80% 60% at 50% 30%, #0f172a 0%, #030712 70%);
}
.layer-grid {
  background-image:
    linear-gradient(rgba(255,255,255,.03) 1px, transparent 1px),
    linear-gradient(90deg, rgba(255,255,255,.03) 1px, transparent 1px);
  background-size: 60px 60px;
}
.layer-glow1 {
  width: 800px; height: 800px;
  background: radial-gradient(circle, rgba(245,158,11,.12) 0%, transparent 70%);
  top: -200px; left: 50%;
  border-radius: 50%;
}
.layer-glow2 {
  width: 600px; height: 600px;
  background: radial-gradient(circle, rgba(99,102,241,.1) 0%, transparent 70%);
  top: 20%; left: 30%;
  border-radius: 50%;
}
.layer-dots {
  background-image: radial-gradient(rgba(255,255,255,.08) 1px, transparent 1px);
  background-size: 30px 30px;
  opacity: .4;
}

/* Floating cards */
.float-card {
  position: absolute;
  background: rgba(255,255,255,.04);
  border: 1px solid rgba(255,255,255,.1);
  backdrop-filter: blur(12px);
  border-radius: 16px;
  padding: 16px 20px;
  will-change: transform;
  z-index: 5;
}
.card-a { top: 22%; right: 8%; min-width: 160px; }
.card-b { top: 55%; right: 13%; min-width: 140px; }
.card-c { top: 30%; left: 6%; min-width: 130px; }

@media (max-width: 768px) {
  .float-card { display: none; }
}

.fc-label { font-size: 11px; color: #64748b; text-transform: uppercase; letter-spacing: 1px; margin-bottom: 4px; }
.fc-value { font-size: 22px; font-weight: 800; color: #fff; }
.fc-trend { font-size: 12px; color: #34d399; font-weight: 600; margin-top: 2px; }
.fc-sub   { font-size: 12px; color: #64748b; margin-top: 2px; }
.fc-icon  { font-size: 24px; margin-bottom: 6px; }

/* Hero content */
.hero-content {
  position: relative; z-index: 10;
  text-align: center; max-width: 720px; padding: 0 24px;
  will-change: transform;
}
.hero-badge {
  display: inline-flex; align-items: center; gap: 8px;
  border: 1px solid rgba(245,158,11,.3);
  background: rgba(245,158,11,.08);
  color: #fbbf24;
  padding: 6px 16px; border-radius: 999px;
  font-size: 12px; font-weight: 600; letter-spacing: .5px;
  margin-bottom: 28px;
}
.badge-dot {
  width: 7px; height: 7px;
  background: #f59e0b; border-radius: 50%;
  animation: pulse 2s infinite;
}
@keyframes pulse {
  0%,100% { opacity: 1; transform: scale(1); }
  50%      { opacity: .4; transform: scale(.8); }
}

.hero-title {
  font-size: clamp(40px, 6vw, 72px);
  font-weight: 800; line-height: 1.1;
  letter-spacing: -2px; color: #fff;
  margin-bottom: 20px;
}
.gradient-text {
  background: linear-gradient(135deg, #f59e0b, #fb923c, #f43f5e);
  -webkit-background-clip: text;
  -webkit-text-fill-color: transparent;
  background-clip: text;
}
.hero-desc {
  font-size: 18px; color: #94a3b8; line-height: 1.7;
  max-width: 560px; margin: 0 auto 36px;
}
.hero-actions {
  display: flex; align-items: center; gap: 16px;
  justify-content: center; flex-wrap: wrap;
  margin-bottom: 28px;
}
.btn-primary {
  display: inline-flex; align-items: center; gap: 8px;
  background: #f59e0b; color: #0f172a;
  padding: 14px 28px; border-radius: 12px;
  font-size: 15px; font-weight: 700; text-decoration: none;
  transition: all .2s;
  box-shadow: 0 0 30px rgba(245,158,11,.3);
}
.btn-primary:hover { background: #fbbf24; transform: translateY(-2px); box-shadow: 0 0 40px rgba(245,158,11,.5); }
.btn-primary.btn-large { padding: 16px 36px; font-size: 16px; }
.btn-ghost {
  color: #94a3b8; font-size: 15px; font-weight: 600; text-decoration: none;
  transition: color .2s;
}
.btn-ghost:hover { color: #fff; }
.hero-badges {
  display: flex; gap: 24px; justify-content: center; flex-wrap: wrap;
}
.hero-badges span { font-size: 13px; color: #475569; }

/* Scroll indicator */
.hero-scroll {
  position: absolute; bottom: 32px; left: 50%; transform: translateX(-50%);
  display: flex; flex-direction: column; align-items: center; gap: 8px;
  color: #475569; font-size: 12px; letter-spacing: 1px; text-transform: uppercase;
  animation: bounce 2s infinite;
}
.scroll-arrow {
  width: 20px; height: 20px;
  border-right: 2px solid #475569; border-bottom: 2px solid #475569;
  transform: rotate(45deg);
}
@keyframes bounce {
  0%,100% { transform: translateX(-50%) translateY(0); }
  50%      { transform: translateX(-50%) translateY(8px); }
}


/* ═══════════════ SECTION COMMONS ═══════════════ */
.section-inner { max-width: 1200px; margin: 0 auto; padding: 0 32px; }
.section-tag {
  display: inline-block;
  border: 1px solid rgba(245,158,11,.3);
  color: #f59e0b; padding: 4px 14px; border-radius: 999px;
  font-size: 12px; font-weight: 600; letter-spacing: 1px; text-transform: uppercase;
  margin-bottom: 16px;
}
.section-tag.light { border-color: rgba(255,255,255,.2); color: rgba(255,255,255,.7); }
.section-title { font-size: clamp(28px,4vw,42px); font-weight: 800; color: #fff; letter-spacing: -1px; margin-bottom: 12px; }
.section-title.light { color: #fff; }
.section-sub { color: #64748b; font-size: 16px; margin-bottom: 48px; }

/* ═══════════════ FEATURES ═══════════════ */
.features { padding: 100px 0; }
.features-grid {
  display: grid;
  grid-template-columns: repeat(3, 1fr);
  grid-template-rows: auto auto;
  gap: 16px;
}
.feat-card {
  background: rgba(255,255,255,.03);
  border: 1px solid rgba(255,255,255,.07);
  border-radius: 16px;
  padding: 28px;
  transition: all .3s;
  position: relative;
}
.feat-card:hover {
  background: rgba(255,255,255,.05);
  border-color: rgba(255,255,255,.12);
  transform: translateY(-4px);
}
.feat-main { grid-column: span 2; }
.feat-ai { border-color: rgba(245,158,11,.2); }
.feat-ai:hover { border-color: rgba(245,158,11,.4); }
.feat-icon-wrap {
  width: 52px; height: 52px; border-radius: 12px;
  display: flex; align-items: center; justify-content: center;
  margin-bottom: 16px;
}
.feat-card h3 { font-size: 16px; font-weight: 700; color: #fff; margin-bottom: 8px; }
.feat-card p  { font-size: 13.5px; color: #64748b; line-height: 1.6; }
.ai-badge {
  position: absolute; top: 16px; right: 16px;
  background: rgba(245,158,11,.15); color: #f59e0b;
  border: 1px solid rgba(245,158,11,.3);
  padding: 2px 10px; border-radius: 999px; font-size: 11px; font-weight: 700;
}
.feat-visual {
  display: flex; gap: 12px; margin-top: 20px; flex-wrap: wrap;
}
.mini-kpi {
  background: rgba(255,255,255,.05); border: 1px solid rgba(255,255,255,.08);
  border-radius: 10px; padding: 10px 16px; text-align: center;
}
.mini-kpi span  { display: block; font-size: 18px; font-weight: 800; color: #fff; }
.mini-kpi label { font-size: 10px; color: #64748b; text-transform: uppercase; }
.mini-kpi.red span { color: #f87171; }
.mini-kpi.green span { color: #34d399; }

/* ═══════════════ STATS ═══════════════ */
.stats { position: relative; padding: 100px 0; overflow: hidden; }
.stats-bg {
  position: absolute; inset: 0;
  background: linear-gradient(135deg, #0f172a 0%, #1e1b4b 50%, #0f172a 100%);
}
.stats-inner { position: relative; z-index: 1; text-align: center; }
.stats-grid { display: grid; grid-template-columns: repeat(4,1fr); gap: 24px; margin-top: 48px; }
.stat-card {
  background: rgba(255,255,255,.04); border: 1px solid rgba(255,255,255,.08);
  border-radius: 16px; padding: 32px 20px; text-align: center;
  transition: all .3s;
}
.stat-card:hover { background: rgba(255,255,255,.07); transform: translateY(-4px); }
.stat-number { font-size: 44px; font-weight: 800; color: #f59e0b; letter-spacing: -2px; }
.stat-label  { font-size: 14px; font-weight: 700; color: #e2e8f0; margin: 8px 0 4px; }
.stat-desc   { font-size: 12px; color: #64748b; }

/* ═══════════════ HOW ═══════════════ */
.how { padding: 100px 0; }
.steps-grid { display: grid; grid-template-columns: repeat(3,1fr); gap: 32px; margin-top: 48px; }
.step { position: relative; text-align: center; padding: 32px 24px; }
.step-num {
  font-size: 64px; font-weight: 900; color: rgba(245,158,11,.1);
  letter-spacing: -3px; line-height: 1; margin-bottom: 12px;
}
.step-icon { font-size: 36px; margin-bottom: 16px; }
.step h3 { font-size: 17px; font-weight: 700; color: #fff; margin-bottom: 8px; }
.step p  { font-size: 14px; color: #64748b; line-height: 1.6; }

/* ═══════════════ PLANS ═══════════════ */
.plans { padding: 100px 0; }
.plans-grid { display: grid; grid-template-columns: repeat(4,1fr); gap: 16px; margin-top: 48px; }
.plan-card {
  background: rgba(255,255,255,.03); border: 1px solid rgba(255,255,255,.07);
  border-radius: 20px; padding: 32px 28px; position: relative;
  transition: all .3s;
}
.plan-card:hover { transform: translateY(-6px); }
.plan-card.featured {
  background: rgba(245,158,11,.06);
  border-color: rgba(245,158,11,.3);
  box-shadow: 0 0 60px rgba(245,158,11,.1);
}
.plan-badge {
  position: absolute; top: -12px; left: 50%; transform: translateX(-50%);
  background: #f59e0b; color: #0f172a;
  padding: 4px 16px; border-radius: 999px; font-size: 11px; font-weight: 800;
  white-space: nowrap;
}
.plan-name { font-size: 13px; font-weight: 700; color: #94a3b8; text-transform: uppercase; letter-spacing: 1px; margin-bottom: 16px; }
.plan-price { margin-bottom: 24px; }
.price-annual-row { display: flex; align-items: baseline; gap: 4px; }
.price-amount { font-size: 42px; font-weight: 900; color: #fff; letter-spacing: -2px; }
.price-unit   { font-size: 13px; color: #64748b; }
.price-monthly { font-size: 12px; color: #475569; margin-top: 2px; }
.plan-features { list-style: none; padding: 0; margin-bottom: 28px; display: flex; flex-direction: column; gap: 10px; }
.plan-features li { display: flex; align-items: center; gap: 10px; font-size: 13.5px; color: #94a3b8; }
.plan-features li svg { color: #34d399; flex-shrink: 0; }
.plan-btn {
  display: block; text-align: center;
  border: 1px solid rgba(255,255,255,.1); color: #94a3b8;
  padding: 12px; border-radius: 10px; text-decoration: none;
  font-size: 14px; font-weight: 600; transition: all .2s;
}
.plan-btn:hover { background: rgba(255,255,255,.05); color: #fff; }
.plan-btn-primary {
  background: #f59e0b; color: #0f172a; border-color: transparent;
}
.plan-btn-primary:hover { background: #fbbf24; color: #0f172a; }

/* ═══════════════ CTA ═══════════════ */
.cta-section {
  position: relative; padding: 120px 32px; text-align: center; overflow: hidden;
}
.cta-bg {
  position: absolute; inset: 0; pointer-events: none;
  background: radial-gradient(ellipse 80% 80% at 50% 50%, rgba(245,158,11,.07) 0%, transparent 70%);
}
.cta-glow {
  position: absolute; width: 400px; height: 400px; pointer-events: none;
  background: radial-gradient(circle, rgba(245,158,11,.15) 0%, transparent 70%);
  top: 50%; left: 50%; transform: translate(-50%,-50%);
  border-radius: 50%;
}
.cta-inner { position: relative; z-index: 1; max-width: 600px; margin: 0 auto; }
.cta-inner h2 { font-size: clamp(28px,4vw,44px); font-weight: 800; color: #fff; letter-spacing: -1px; margin-bottom: 16px; }
.cta-inner p  { font-size: 16px; color: #64748b; margin-bottom: 36px; }

/* ═══════════════ FOOTER ═══════════════ */
.footer {
  border-top: 1px solid rgba(255,255,255,.05);
  padding: 32px;
}
.footer-inner {
  max-width: 1200px; margin: 0 auto;
  display: flex; align-items: center; justify-content: space-between; flex-wrap: wrap; gap: 16px;
}
.footer-logo { display: flex; align-items: center; gap: 8px; font-size: 16px; font-weight: 700; color: #fff; }
.footer-copy { font-size: 12px; color: #475569; }
.footer-link { font-size: 13px; color: #f59e0b; text-decoration: none; font-weight: 600; }

/* ═══════════════ RESPONSIVE ═══════════════ */
@media (max-width: 1100px) {
  .plans-grid { grid-template-columns: repeat(2,1fr); }
}
@media (max-width: 900px) {
  .features-grid { grid-template-columns: 1fr 1fr; }
  .feat-main { grid-column: span 2; }
  .stats-grid  { grid-template-columns: repeat(2,1fr); }
  .steps-grid  { grid-template-columns: 1fr; }
  .nav-links   { display: none; }
}
@media (max-width: 600px) {
  .plans-grid { grid-template-columns: 1fr; max-width: 400px; margin-left: auto; margin-right: auto; }
  .features-grid { grid-template-columns: 1fr; }
  .feat-main { grid-column: span 1; }
  .stats-grid { grid-template-columns: 1fr 1fr; }
}

/* ═══════════════ MODAL ═══════════════ */
.modal-overlay {
  position: fixed; inset: 0; z-index: 999;
  background: rgba(3,7,18,.8); backdrop-filter: blur(8px);
  display: flex; align-items: center; justify-content: center;
  padding: 20px;
}
.modal-box {
  background: #0f172a;
  border: 1px solid rgba(255,255,255,.1);
  border-radius: 20px;
  padding: 36px;
  width: 100%; max-width: 600px;
  max-height: 90vh; overflow-y: auto;
  position: relative;
}
.modal-close {
  position: absolute; top: 16px; right: 16px;
  background: rgba(255,255,255,.07); border: none;
  color: #94a3b8; width: 32px; height: 32px;
  border-radius: 50%; cursor: pointer; font-size: 14px;
  transition: all .2s;
}
.modal-close:hover { background: rgba(255,255,255,.12); color: #fff; }

.modal-header {
  display: flex; align-items: center; gap: 14px; margin-bottom: 24px;
}
.modal-header h2 { font-size: 18px; font-weight: 800; color: #fff; margin-bottom: 2px; }
.modal-header p  { font-size: 13px; color: #64748b; }

.form-error {
  background: rgba(239,68,68,.1); border: 1px solid rgba(239,68,68,.3);
  color: #fca5a5; padding: 10px 14px; border-radius: 8px;
  font-size: 13px; margin-bottom: 16px;
}

.demo-form { display: flex; flex-direction: column; gap: 14px; }
.form-row  { display: grid; grid-template-columns: 1fr 1fr; gap: 12px; }
.form-group { display: flex; flex-direction: column; gap: 5px; }
.form-group label { font-size: 12px; font-weight: 600; color: #94a3b8; text-transform: uppercase; letter-spacing: .5px; }
.form-group input,
.form-group textarea {
  background: rgba(255,255,255,.05);
  border: 1px solid rgba(255,255,255,.1);
  border-radius: 8px; padding: 10px 12px;
  color: #fff; font-size: 13.5px;
  outline: none; transition: border-color .2s;
  font-family: inherit; resize: vertical;
}
.form-group input::placeholder,
.form-group textarea::placeholder { color: #475569; }
.form-group input:focus,
.form-group textarea:focus { border-color: #f59e0b; }

.plan-select { display: flex; gap: 8px; }
.plan-opt {
  flex: 1; padding: 8px; border-radius: 8px; cursor: pointer;
  border: 1px solid rgba(255,255,255,.1);
  background: rgba(255,255,255,.03);
  color: #64748b; font-size: 13px; font-weight: 600;
  transition: all .2s;
}
.plan-opt.active {
  border-color: #f59e0b; background: rgba(245,158,11,.1); color: #fbbf24;
}

.submit-btn {
  background: #f59e0b; color: #0f172a;
  border: none; border-radius: 10px;
  padding: 13px; font-size: 15px; font-weight: 700;
  cursor: pointer; transition: all .2s; margin-top: 4px;
}
.submit-btn:hover:not(:disabled) { background: #fbbf24; transform: translateY(-1px); }
.submit-btn:disabled { opacity: .6; cursor: not-allowed; }

/* Succès */
.modal-success { text-align: center; padding: 20px 0; }
.success-icon {
  width: 64px; height: 64px; background: rgba(52,211,153,.1);
  border: 2px solid #34d399; border-radius: 50%;
  display: flex; align-items: center; justify-content: center;
  font-size: 28px; color: #34d399; margin: 0 auto 20px;
}
.modal-success h2 { font-size: 22px; font-weight: 800; color: #fff; margin-bottom: 10px; }
.modal-success p  { color: #94a3b8; font-size: 14px; line-height: 1.7; margin-bottom: 24px; }
.modal-success strong { color: #f59e0b; }

/* Transition */
.modal-enter-active, .modal-leave-active { transition: all .25s ease; }
.modal-enter-from, .modal-leave-to { opacity: 0; }
.modal-enter-from .modal-box, .modal-leave-to .modal-box { transform: scale(.95) translateY(10px); }

@media (max-width: 520px) {
  .form-row { grid-template-columns: 1fr; }
  .modal-box { padding: 24px; }
}
</style>