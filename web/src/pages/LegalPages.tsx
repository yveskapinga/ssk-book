import { Link } from 'react-router-dom'
import type { ReactNode } from 'react'
import { Brand } from '../components/Brand'

const CONTACT = 'yveskapinga@gmail.com'
const APP = 'SSK Book'
const DOMAIN = 'https://ssk-book.yabisoo.com'

function LegalShell({
  title,
  children,
}: {
  title: string
  children: ReactNode
}) {
  return (
    <div className="public-shell">
      <header className="public-header">
        <Brand to="/" />
        <nav className="public-nav">
          <Link to="/legal/privacy">Confidentialité</Link>
          <Link to="/legal/terms">Conditions</Link>
          <Link to="/legal/delete-account">Suppression du compte</Link>
          <Link className="ghost" to="/connexion">
            Se connecter
          </Link>
        </nav>
      </header>
      <main className="page legal-page">
        <span className="eyebrow">Informations légales</span>
        <h1>{title}</h1>
        <p className="legal-updated">Dernière mise à jour : 5 octobre 2026</p>
        <article className="legal-article">{children}</article>
        <p className="legal-related">
          <Link to="/legal/privacy">Politique de confidentialité</Link>
          {' · '}
          <Link to="/legal/terms">Conditions d’utilisation</Link>
          {' · '}
          <Link to="/legal/delete-account">Suppression du compte</Link>
        </p>
      </main>
    </div>
  )
}

export function PrivacyPage() {
  return (
    <LegalShell title="Politique de confidentialité">
      <section>
        <h2>1. Qui est responsable ?</h2>
        <p>
          L’application {APP} ({DOMAIN}) est éditée par Yves Kapinga. Contact :{' '}
          <a href={`mailto:${CONTACT}`}>{CONTACT}</a>.
        </p>
      </section>
      <section>
        <h2>2. Données collectées</h2>
        <p>Nous pouvons traiter :</p>
        <ul>
          <li>données de compte (e-mail, nom affiché, mot de passe hashé) ;</li>
          <li>progression de lecture, favoris, notes, surlignages ;</li>
          <li>questions posées au livre et réponses associées ;</li>
          <li>tentatives de quiz ;</li>
          <li>jetons de notification push (appareil) si vous les activez ;</li>
          <li>journaux techniques limités (sécurité, diagnostic).</li>
        </ul>
      </section>
      <section>
        <h2>3. Finalités</h2>
        <p>
          Ces données servent à fournir le service de lecture, la synchronisation
          multi-appareils, les quiz, les réponses fondées sur le livre, les
          notifications opt-in, et à assurer la sécurité du service.
        </p>
      </section>
      <section>
        <h2>4. Base légale et conservation</h2>
        <p>
          Le traitement repose sur l’exécution du contrat (votre compte) et, le
          cas échéant, votre consentement (notifications). Les données sont
          conservées tant que le compte est actif, puis anonymisées ou
          supprimées après demande de suppression, sauf obligations légales.
        </p>
      </section>
      <section>
        <h2>5. Sous-traitants / services</h2>
        <p>
          L’hébergement et l’infrastructure (serveur, certificats) ainsi que des
          services d’IA pour les réponses « Demander au livre » peuvent traiter
          des contenus de questions/passages. Les notifications passent par les
          services Expo / Google selon l’appareil.
        </p>
      </section>
      <section>
        <h2>6. Vos droits</h2>
        <p>
          Vous pouvez accéder à vos données, les rectifier, ou demander la
          suppression de votre compte via{' '}
          <Link to="/legal/delete-account">cette page</Link> ou dans l’application
          (Profil). Contact : <a href={`mailto:${CONTACT}`}>{CONTACT}</a>.
        </p>
      </section>
      <section>
        <h2>7. Sécurité</h2>
        <p>
          Les mots de passe sont stockés sous forme hashée. Les sessions utilisent
          des jetons d’accès. Aucune garantie absolue : signalez tout incident à{' '}
          <a href={`mailto:${CONTACT}`}>{CONTACT}</a>.
        </p>
      </section>
    </LegalShell>
  )
}

export function TermsPage() {
  return (
    <LegalShell title="Conditions d’utilisation">
      <section>
        <h2>1. Objet</h2>
        <p>
          {APP} permet de lire le livre du Souverain Sacrificateur KADIMA, de
          suivre sa progression, de poser des questions fondées sur le texte et
          de passer des quiz validés.
        </p>
      </section>
      <section>
        <h2>2. Compte</h2>
        <p>
          Vous êtes responsable de la confidentialité de vos identifiants. Un
          compte peut être suspendu en cas d’abus (fraude, atteinte au service
          ou aux droits de tiers).
        </p>
      </section>
      <section>
        <h2>3. Contenu</h2>
        <p>
          Le contenu du livre et les éléments liés restent la propriété de leurs
          titulaires. Vous disposez d’une licence d’usage personnelle,
          non exclusive, pour la lecture via {APP}.
        </p>
      </section>
      <section>
        <h2>4. Fonctionnalités IA</h2>
        <p>
          Les réponses « Demander au livre » s’appuient sur les passages publiés.
          Elles peuvent être incomplètes ; elles ne remplacent pas une lecture
          attentive du texte source.
        </p>
      </section>
      <section>
        <h2>5. Disponibilité</h2>
        <p>
          Le service est fourni « en l’état ». Des interruptions peuvent
          survenir pour maintenance ou causes indépendantes de notre volonté.
        </p>
      </section>
      <section>
        <h2>6. Contact</h2>
        <p>
          Questions : <a href={`mailto:${CONTACT}`}>{CONTACT}</a>.
        </p>
      </section>
    </LegalShell>
  )
}

export function DeleteAccountPage() {
  return (
    <LegalShell title="Suppression du compte et des données">
      <section>
        <h2>Pourquoi cette page ?</h2>
        <p>
          Google Play exige un moyen clair de demander la suppression du compte
          et des données associées pour les applications qui permettent la
          création de compte.
        </p>
      </section>
      <section>
        <h2>Comment supprimer votre compte</h2>
        <ol>
          <li>
            <strong>Dans l’application mobile</strong> : Profil → Supprimer mon
            compte (confirmation requise).
          </li>
          <li>
            <strong>Par e-mail</strong> : écrivez à{' '}
            <a href={`mailto:${CONTACT}?subject=Suppression%20compte%20SSK%20Book`}>
              {CONTACT}
            </a>{' '}
            depuis l’adresse utilisée pour le compte, avec l’objet « Suppression
            compte SSK Book ».
          </li>
        </ol>
      </section>
      <section>
        <h2>Ce qui est supprimé</h2>
        <ul>
          <li>identifiants de compte (e-mail / nom anonymisés) ;</li>
          <li>progression, favoris, notes, surlignages ;</li>
          <li>conversations / questions ;</li>
          <li>tentatives de quiz et jetons push.</li>
        </ul>
        <p>
          Le traitement est effectué sans délai injustifié (en pratique
          immédiat via l’app, ou sous quelques jours ouvrés par e-mail).
        </p>
      </section>
      <section>
        <h2>Après suppression</h2>
        <p>
          Vous ne pourrez plus vous connecter avec ce compte. Pour réutiliser
          l’app, créez un nouveau compte.
        </p>
      </section>
      <p className="legal-cta">
        <Link className="primary" to="/connexion">
          Se connecter pour supprimer depuis l’espace
        </Link>
      </p>
    </LegalShell>
  )
}
