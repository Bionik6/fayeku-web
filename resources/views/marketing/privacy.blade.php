<x-layouts.marketing :meta-title="$metaTitle" :meta-description="$metaDescription" :canonical-url="$canonicalUrl">
    <section class="py-20">
        <div class="mx-auto w-full max-w-4xl space-y-10 px-4 sm:px-6 lg:px-8">

            {{-- En-tête --}}
            <div class="space-y-4">
                <p class="text-sm font-semibold uppercase tracking-[0.24em] text-teal">Légal</p>
                <div class="space-y-3">
                    <h1 class="text-balance text-3xl font-semibold text-ink sm:text-4xl">Politique de confidentialité</h1>
                    <p class="text-pretty text-base leading-7 text-slate-600 sm:text-lg">Comment Fayeku collecte, utilise et protège vos données personnelles — dernière mise à jour&nbsp;: {{ now()->format('d/m/Y') }}.</p>
                </div>
            </div>

            {{-- Introduction --}}
            <div class="rounded-4xl border border-primary/10 bg-white p-8 shadow-soft">
                <div class="space-y-4 text-base leading-7 text-slate-600">
                    <p>Fayeku accorde une importance particulière à la protection des données personnelles et veille au respect de la réglementation applicable, notamment la <strong class="text-ink">Loi n°&nbsp;2008-12 du 25&nbsp;janvier&nbsp;2008 sur la protection des données à caractère personnel</strong> au Sénégal, ainsi que les textes régionaux de la CEDEAO relatifs aux transactions électroniques et à la protection des données.</p>
                    <p>Les utilisateurs sont invités à lire attentivement la présente politique, qui contient des informations importantes sur la façon dont leurs données personnelles sont collectées, utilisées et partagées.</p>
                </div>
            </div>

            {{-- Sections --}}
            <div class="space-y-6">

                {{-- Définitions --}}
                <article class="rounded-4xl border border-primary/10 bg-white p-8 shadow-soft">
                    <h2 class="text-xl font-semibold text-ink">Définitions</h2>
                    <dl class="mt-4 space-y-4 text-base leading-7 text-slate-600">
                        <div>
                            <dt class="font-semibold text-ink">Consentement éclairé</dt>
                            <dd class="mt-1">Manifestation de volonté libre, spécifique et informée par laquelle la personne accepte que des données la concernant fassent l'objet d'un traitement.</dd>
                        </div>
                        <div>
                            <dt class="font-semibold text-ink">Données sensibles</dt>
                            <dd class="mt-1">Données révélant l'origine raciale ou ethnique, les opinions politiques, les convictions religieuses ou philosophiques, l'appartenance syndicale, les données génétiques ou biométriques, les données de santé ou relatives à la vie sexuelle.</dd>
                        </div>
                        <div>
                            <dt class="font-semibold text-ink">Personne concernée</dt>
                            <dd class="mt-1">Toute personne physique identifiée ou identifiable dont les données font l'objet d'un traitement.</dd>
                        </div>
                        <div>
                            <dt class="font-semibold text-ink">Traitement</dt>
                            <dd class="mt-1">Toute opération ou ensemble d'opérations effectuées sur des données personnelles, automatisées ou non : collecte, enregistrement, organisation, conservation, adaptation, modification, extraction, consultation, utilisation, communication, diffusion, effacement ou destruction.</dd>
                        </div>
                        <div>
                            <dt class="font-semibold text-ink">Responsable de traitement</dt>
                            <dd class="mt-1">La personne physique ou morale qui détermine les finalités et les moyens du traitement des données personnelles.</dd>
                        </div>
                        <div>
                            <dt class="font-semibold text-ink">Sous-traitant</dt>
                            <dd class="mt-1">La personne physique ou morale qui traite des données personnelles pour le compte du responsable de traitement.</dd>
                        </div>
                        <div>
                            <dt class="font-semibold text-ink">Vous / Utilisateur</dt>
                            <dd class="mt-1">Toute personne physique dont les données sont traitées, notamment les entrepreneurs, PME clientes, cabinets d'expertise comptable, visiteurs du site et prospects.</dd>
                        </div>
                    </dl>
                </article>

                {{-- Article 1 --}}
                <article class="rounded-4xl border border-primary/10 bg-white p-8 shadow-soft">
                    <h2 class="text-xl font-semibold text-ink">Article 1 — Informations sur l'entreprise</h2>
                    <div class="mt-4 space-y-4 text-base leading-7 text-slate-600">
                        <p><strong class="text-ink">1.1</strong> Fayeku est un service édité par <strong class="text-ink">{{ $site['legal']['company'] }}</strong>, entreprise de droit sénégalais, dont le siège social est situé à {{ $site['contact']['address'] }}, immatriculée sous le NINEA <strong class="text-ink">{{ $site['legal']['ninea'] }}</strong> et le RCCM <strong class="text-ink">{{ $site['legal']['rccm'] }}</strong>.</p>
                        <p><strong class="text-ink">1.2</strong> Pour toute question relative à la protection de vos données personnelles, vous pouvez contacter le responsable de traitement à l'adresse suivante&nbsp;: <a href="mailto:{{ $site['contact']['email'] }}" class="text-primary hover:underline">{{ $site['contact']['email'] }}</a> ou par courrier à l'adresse du siège social.</p>
                    </div>
                </article>

                {{-- Article 2 --}}
                <article class="rounded-4xl border border-primary/10 bg-white p-8 shadow-soft">
                    <h2 class="text-xl font-semibold text-ink">Article 2 — Champ d'application</h2>
                    <div class="mt-4 space-y-4 text-base leading-7 text-slate-600">
                        <p><strong class="text-ink">2.1 Objet.</strong> La présente politique informe les utilisateurs de la solution Fayeku, les bénéficiaires des services, ainsi que les visiteurs du site fayeku.sn, sur la manière dont Fayeku utilise et protège leurs données personnelles. Elle s'applique aux PME clientes, cabinets d'expertise comptable partenaires, prospects, fournisseurs et à toute personne entrant en contact avec Fayeku.</p>
                        <p><strong class="text-ink">2.2 Limite.</strong> La présente politique ne régit que les traitements pour lesquels Fayeku agit en qualité de responsable de traitement. Les traitements effectués pour le compte des clients (en qualité de sous-traitant) sont encadrés par les Conditions Générales d'Utilisation et les conventions de traitement de données.</p>
                    </div>
                </article>

                {{-- Article 3 --}}
                <article class="rounded-4xl border border-primary/10 bg-white p-8 shadow-soft">
                    <h2 class="text-xl font-semibold text-ink">Article 3 — Rôles de Fayeku dans le traitement des données</h2>
                    <div class="mt-4 space-y-4 text-base leading-7 text-slate-600">
                        <p>Au regard de la Loi n°&nbsp;2008-12, Fayeku peut intervenir en deux qualités distinctes&nbsp;:</p>
                        <p><strong class="text-ink">Responsable de traitement</strong>&nbsp;: Fayeku détermine seul les finalités et les moyens du traitement. C'est le cas notamment pour la gestion des comptes, la prospection commerciale, la sécurité de la plateforme et l'amélioration des services.</p>
                        <p><strong class="text-ink">Sous-traitant (traitement initial)</strong>&nbsp;: Lorsque la PME ou le cabinet d'expertise comptable utilise la solution pour gérer ses propres données (factures clients, pièces comptables, contacts), c'est la PME ou le cabinet qui agit en qualité de responsable de traitement, et Fayeku traite les données selon ses instructions.</p>
                        <p><strong class="text-ink">Responsable de traitement (traitement secondaire)</strong>&nbsp;: Fayeku peut réutiliser les données initialement collectées dans le cadre de la prestation à des fins d'amélioration du service, de statistiques agrégées et d'entraînement de modèles d'assistance. Ces traitements secondaires sont compatibles avec les traitements initiaux compte tenu du lien entre les finalités, de l'absence de données sensibles, des conséquences limitées pour les personnes concernées et des garanties appropriées mises en œuvre.</p>
                    </div>
                </article>

                {{-- Article 4 --}}
                <article class="rounded-4xl border border-primary/10 bg-white p-8 shadow-soft">
                    <h2 class="text-xl font-semibold text-ink">Article 4 — Modalités de collecte des données</h2>
                    <div class="mt-4 space-y-4 text-base leading-7 text-slate-600">
                        <p><strong class="text-ink">4.1 Collecte directe</strong>, notamment lors de&nbsp;:</p>
                        <ul class="ml-6 list-disc space-y-1">
                            <li>la création et la gestion d'un compte Fayeku (PME ou cabinet) ;</li>
                            <li>l'utilisation de la solution et des services associés (facturation, devis, relances, trésorerie) ;</li>
                            <li>la soumission d'un formulaire de contact ou d'une demande de rejoindre Fayeku Compta ;</li>
                            <li>les échanges avec l'équipe Fayeku par email, téléphone ou WhatsApp.</li>
                        </ul>
                        <p><strong class="text-ink">4.2 Collecte indirecte</strong>&nbsp;: certaines données peuvent être transmises à Fayeku par des clients ou partenaires qui saisissent vos informations dans la solution dans le cadre de leur activité comptable ou de facturation.</p>
                        <p><strong class="text-ink">4.3 Collecte automatique</strong>&nbsp;: lors de la navigation sur fayeku.sn et de l'utilisation de la solution, certaines données de connexion sont collectées automatiquement via des traceurs (voir Article&nbsp;7).</p>
                    </div>
                </article>

                {{-- Article 5 --}}
                <article class="rounded-4xl border border-primary/10 bg-white p-8 shadow-soft">
                    <h2 class="text-xl font-semibold text-ink">Article 5 — Données personnelles collectées</h2>
                    <div class="mt-4 space-y-4 text-base leading-7 text-slate-600">
                        <p><strong class="text-ink">5.1</strong> Le détail des données traitées figure à l'Annexe 1 de la présente politique.</p>
                        <p><strong class="text-ink">5.2 Caractère obligatoire.</strong> Certaines données personnelles sont nécessaires à la création de compte et à la fourniture des services Fayeku. Leur refus ou la limitation de leur traitement peut empêcher l'accès à tout ou partie de la plateforme, ou empêcher Fayeku de répondre à vos demandes.</p>
                        <p><strong class="text-ink">5.3 Minimisation.</strong> Fayeku s'assure que les données collectées sont pertinentes, adéquates et non excessives au regard des finalités poursuivies. Seules les informations strictement nécessaires à ces finalités sont collectées et traitées.</p>
                    </div>
                </article>

                {{-- Article 6 --}}
                <article class="rounded-4xl border border-primary/10 bg-white p-8 shadow-soft">
                    <h2 class="text-xl font-semibold text-ink">Article 6 — Finalités et bases légales du traitement</h2>
                    <div class="mt-4 space-y-4 text-base leading-7 text-slate-600">
                        <p><strong class="text-ink">6.1 Bases légales.</strong> Fayeku ne traite les données personnelles que sur l'une des bases suivantes&nbsp;:</p>
                        <ul class="ml-6 list-disc space-y-1">
                            <li>l'exécution d'un contrat auquel la personne concernée est partie, ou l'exécution de mesures précontractuelles ;</li>
                            <li>le respect d'une obligation légale à laquelle Fayeku est soumis ;</li>
                            <li>la poursuite d'intérêts légitimes de Fayeku, qui ne portent pas atteinte aux droits fondamentaux des personnes ;</li>
                            <li>le consentement de la personne concernée, lorsqu'il est explicitement requis.</li>
                        </ul>
                        <p><strong class="text-ink">6.2 Finalités.</strong> Les finalités de chaque traitement sont décrites à l'Annexe&nbsp;1. Les données ne font pas l'objet de traitements ultérieurs incompatibles avec ces finalités.</p>
                    </div>
                </article>

                {{-- Article 7 --}}
                <article class="rounded-4xl border border-primary/10 bg-white p-8 shadow-soft">
                    <h2 class="text-xl font-semibold text-ink">Article 7 — Cookies et traceurs</h2>
                    <div class="mt-4 space-y-4 text-base leading-7 text-slate-600">
                        <p>Fayeku utilise des technologies de traceurs sur son site pour mesurer l'audience, améliorer la navigation et analyser l'utilisation des fonctionnalités. Ces traceurs peuvent avoir des finalités analytiques ou publicitaires, sous réserve du consentement exprimé via l'outil de gestion des cookies.</p>
                        <p>Des traceurs strictement nécessaires au fonctionnement de la plateforme (authentification, sécurité, préférences) sont déposés sans consentement préalable, conformément à la réglementation applicable.</p>
                        <p>Vous pouvez à tout moment modifier vos préférences en matière de traceurs depuis le gestionnaire de consentement disponible sur le site ou en contactant <a href="mailto:{{ $site['contact']['email'] }}" class="text-primary hover:underline">{{ $site['contact']['email'] }}</a>.</p>
                    </div>
                </article>

                {{-- Article 8 --}}
                <article class="rounded-4xl border border-primary/10 bg-white p-8 shadow-soft">
                    <h2 class="text-xl font-semibold text-ink">Article 8 — Durées de conservation</h2>
                    <div class="mt-4 space-y-4 text-base leading-7 text-slate-600">
                        <p><strong class="text-ink">8.1</strong> Les données personnelles sont conservées pendant la durée nécessaire à la réalisation des finalités décrites à l'Annexe&nbsp;1. À l'issue de ces durées, elles peuvent être conservées pour&nbsp;:</p>
                        <ul class="ml-6 list-disc space-y-1">
                            <li>le respect des obligations légales, comptables et fiscales (10&nbsp;ans à compter de la clôture de l'exercice comptable concerné, conformément au Code général des impôts sénégalais) ;</li>
                            <li>la constitution de preuves pendant les délais de prescription applicables (5&nbsp;ans à compter de la fin des durées de conservation initiales).</li>
                        </ul>
                        <p><strong class="text-ink">8.2</strong> Les tiers traitant des données pour le compte de Fayeku ne les conservent que le temps nécessaire à l'accomplissement des finalités énoncées.</p>
                    </div>
                </article>

                {{-- Article 9 --}}
                <article class="rounded-4xl border border-primary/10 bg-white p-8 shadow-soft">
                    <h2 class="text-xl font-semibold text-ink">Article 9 — Mesures de sécurité</h2>
                    <div class="mt-4 space-y-4 text-base leading-7 text-slate-600">
                        <p><strong class="text-ink">9.1</strong> Les données personnelles sont protégées par des mesures techniques et organisationnelles garantissant leur sécurité, leur intégrité et leur confidentialité : chiffrement des données en transit (TLS) et au repos, contrôle d'accès basé sur les rôles, journalisation des accès, authentification à deux facteurs, surveillance continue de l'infrastructure.</p>
                        <p><strong class="text-ink">9.2</strong> Les prestataires et partenaires sont sélectionnés pour offrir des garanties suffisantes quant à la mise en œuvre de mesures de protection appropriées.</p>
                        <p><strong class="text-ink">9.3</strong> La documentation nécessaire à la démonstration de la conformité aux obligations applicables est maintenue à jour.</p>
                        <p><strong class="text-ink">9.4</strong> En cas de violation de données susceptible d'engendrer un risque pour les droits et libertés des personnes, Fayeku notifie les personnes concernées et, le cas échéant, la Commission de Protection des Données Personnelles (CDP) dans les délais légaux. Des mesures techniques et organisationnelles sont immédiatement mises en œuvre pour limiter les conséquences de la violation et prévenir toute récurrence.</p>
                        <p><strong class="text-ink">9.5 Évaluations d'impact.</strong> Avant tout nouveau traitement présentant un risque élevé pour les droits et libertés des personnes, Fayeku procède à une analyse d'impact relative à la protection des données. Tout traitement incompatible avec les principes de la Loi n°&nbsp;2008-12 est écarté.</p>
                    </div>
                </article>

                {{-- Article 10 --}}
                <article class="rounded-4xl border border-primary/10 bg-white p-8 shadow-soft">
                    <h2 class="text-xl font-semibold text-ink">Article 10 — Droits des personnes concernées</h2>
                    <div class="mt-4 space-y-4 text-base leading-7 text-slate-600">
                        <p><strong class="text-ink">10.1 Vos droits.</strong> Conformément à la Loi n°&nbsp;2008-12, vous disposez des droits suivants&nbsp;:</p>
                        <ul class="ml-6 list-disc space-y-3">
                            <li><strong class="text-ink">Droit d'accès.</strong> Vous pouvez à tout moment obtenir la confirmation que des données vous concernant sont ou non traitées, ainsi qu'une copie de ces données.</li>
                            <li><strong class="text-ink">Droit de portabilité.</strong> Vous pouvez demander à récupérer vos données dans un format structuré et couramment utilisé, lorsque le traitement est fondé sur votre consentement ou l'exécution d'un contrat.</li>
                            <li><strong class="text-ink">Droit de rectification.</strong> Vous pouvez demander la correction de données inexactes, incomplètes ou périmées vous concernant.</li>
                            <li><strong class="text-ink">Droit à l'effacement.</strong> Vous pouvez demander la suppression de vos données lorsque&nbsp;: (i) elles ne sont plus nécessaires aux finalités pour lesquelles elles ont été collectées, (ii) vous retirez votre consentement (pour les traitements fondés sur le consentement), (iii) le traitement est illicite, ou (iv) leur suppression est requise par une obligation légale.</li>
                            <li><strong class="text-ink">Droit à la limitation.</strong> Vous pouvez demander la suspension temporaire du traitement en cas de contestation de l'exactitude des données, ou si vous avez besoin de leur conservation pour l'exercice de droits en justice.</li>
                            <li><strong class="text-ink">Droit au retrait du consentement.</strong> Lorsque le traitement est fondé sur votre consentement, vous pouvez le retirer à tout moment sans que cela ne remette en cause la licéité du traitement antérieur.</li>
                            <li><strong class="text-ink">Droit d'opposition.</strong> Vous pouvez vous opposer au traitement de vos données à des fins de prospection commerciale, de publicité ciblée, de partage avec des tiers, ou fondé sur l'intérêt légitime de Fayeku.</li>
                            <li><strong class="text-ink">Sort des données après le décès.</strong> Vous pouvez définir les directives relatives à la conservation et à la communication de vos données après votre décès.</li>
                        </ul>
                        <p>Vous disposez également du droit d'introduire une réclamation auprès de la <strong class="text-ink">Commission de Protection des Données Personnelles (CDP)</strong>, autorité de contrôle compétente au Sénégal, dont le site est accessible à <a href="https://www.cdp.sn" target="_blank" rel="noopener noreferrer" class="text-primary hover:underline">www.cdp.sn</a>.</p>

                        <p><strong class="text-ink">10.2 Exercice de vos droits.</strong></p>
                        <p>Pour toute donnée pour laquelle Fayeku agit en qualité de responsable de traitement, adressez votre demande à&nbsp;: <a href="mailto:{{ $site['contact']['email'] }}" class="text-primary hover:underline">{{ $site['contact']['email'] }}</a>, ou par courrier à {{ $site['contact']['address'] }}. Votre demande doit préciser clairement l'objet (accès, rectification, effacement, etc.) et peut nécessiter la fourniture d'une pièce d'identité à des fins de vérification.</p>
                        <p>Pour toute donnée pour laquelle Fayeku agit en qualité de sous-traitant (données saisies dans la solution par votre cabinet ou votre PME), adressez votre demande directement au responsable de traitement concerné (votre cabinet ou PME partenaire). Fayeku peut apporter son assistance mais ne peut pas répondre directement en pareil cas.</p>
                    </div>
                </article>

                {{-- Article 11 --}}
                <article class="rounded-4xl border border-primary/10 bg-white p-8 shadow-soft">
                    <h2 class="text-xl font-semibold text-ink">Article 11 — Partage et transfert des données</h2>
                    <div class="mt-4 space-y-4 text-base leading-7 text-slate-600">
                        <p><strong class="text-ink">11.1 Usage interne.</strong> Les données personnelles sont traitées par les collaborateurs de Fayeku dans le cadre strict de leurs fonctions et uniquement pour atteindre les finalités déclarées. Tous les collaborateurs sont tenus à une obligation de confidentialité stricte.</p>
                        <p><strong class="text-ink">11.2 Communication à des tiers.</strong> Les données personnelles ne sont communiquées à des tiers que lorsqu'une justification légale existe (consentement accordé, exécution d'un contrat nécessaire, intérêts légitimes de Fayeku). La communication obéit à des règles strictes de « besoin d'en connaître ». Le respect d'une obligation légale ou l'exécution de décisions judiciaires peut également justifier une communication, avec notification à la personne concernée dans la mesure du possible.</p>
                        <p><strong class="text-ink">11.3 Sous-traitants.</strong> Fayeku fait appel à des prestataires de services spécialisés pour l'hébergement, la sécurité, l'envoi de communications et l'analyse des usages. Ces prestataires sont sélectionnés pour les garanties qu'ils offrent et sont liés à Fayeku par des engagements contractuels de confidentialité et de sécurité. Ils n'ont accès qu'aux données strictement nécessaires à leur mission.</p>
                        <p><strong class="text-ink">11.4 Transferts hors Sénégal.</strong> Dans le cadre des finalités déclarées, Fayeku peut faire appel à des sous-traitants dont les serveurs sont situés en dehors du Sénégal, notamment en Europe. Lorsque des transferts sont effectués vers des pays n'offrant pas un niveau de protection adéquat reconnu, Fayeku s'assure de la mise en place de garanties appropriées conformément aux exigences de la Loi n°&nbsp;2008-12 et des recommandations de la CDP.</p>
                        <p><strong class="text-ink">11.5 Liens hypertextes.</strong> Le site fayeku.sn peut contenir des liens vers des sites tiers (réseaux sociaux, partenaires). Ces sites disposent de leurs propres politiques de confidentialité. Fayeku ne peut être tenu responsable de leur non-conformité. Nous vous invitons à consulter leurs politiques avant de leur communiquer des données personnelles.</p>
                    </div>
                </article>

                {{-- Article 12 --}}
                <article class="rounded-4xl border border-primary/10 bg-white p-8 shadow-soft">
                    <h2 class="text-xl font-semibold text-ink">Article 12 — Gestion des réclamations</h2>
                    <div class="mt-4 space-y-4 text-base leading-7 text-slate-600">
                        <p><strong class="text-ink">12.1</strong> Fayeku s'engage à traiter toute réclamation légitime relative à la vie privée. Chaque réclamation relative à une violation potentielle ou effective de la présente politique ou de la législation applicable est examinée, et des mesures raisonnables sont prises pour en limiter les effets.</p>
                        <p><strong class="text-ink">12.2</strong> Si une réclamation n'est pas résolue de manière satisfaisante, Fayeku coopère avec la CDP et se conforme à ses orientations. En cas de constat de non-conformité, Fayeku met en œuvre les mesures correctrices appropriées et prend toutes dispositions pour prévenir toute récidive.</p>
                    </div>
                </article>

                {{-- Article 13 --}}
                <article class="rounded-4xl border border-primary/10 bg-white p-8 shadow-soft">
                    <h2 class="text-xl font-semibold text-ink">Article 13 — Application et modification de la politique</h2>
                    <div class="mt-4 space-y-4 text-base leading-7 text-slate-600">
                        <p><strong class="text-ink">13.1</strong> Fayeku peut modifier, compléter ou mettre à jour la présente politique pour tenir compte d'évolutions légales, réglementaires, jurisprudentielles ou techniques. Pour toute modification substantielle (portant sur les bases légales, les finalités de traitement ou l'exercice des droits), Fayeku notifiera les utilisateurs par écrit au moins 30&nbsp;jours avant son entrée en vigueur. L'utilisation de la solution après cette échéance vaut acceptation des nouvelles conditions. La version en ligne est la seule version authentique.</p>
                        <p><strong class="text-ink">13.2</strong> En visitant le site, en contactant Fayeku, en créant un compte ou en utilisant la solution, vous acceptez les termes de la présente politique.</p>
                    </div>
                </article>

                {{-- Annexe 1 --}}
                <article class="rounded-4xl border border-primary/10 bg-white p-8 shadow-soft">
                    <h2 class="text-xl font-semibold text-ink">Annexe 1 — Tableau des traitements</h2>
                    <p class="mt-2 text-sm text-slate-500">Finalités, données traitées, bases légales et durées de conservation.</p>

                    <div class="mt-6 overflow-x-auto">
                        <table class="w-full min-w-[640px] border-collapse text-sm text-slate-600">
                            <thead>
                                <tr class="border-b border-slate-200 text-left text-xs font-semibold uppercase tracking-wide text-slate-400">
                                    <th class="pb-3 pr-4 align-top">Finalité</th>
                                    <th class="pb-3 pr-4 align-top">Données traitées</th>
                                    <th class="pb-3 pr-4 align-top">Base légale</th>
                                    <th class="pb-3 align-top">Durée</th>
                                </tr>
                            </thead>
                            <tbody class="divide-y divide-slate-100">
                                <tr>
                                    <td class="py-4 pr-4 align-top font-medium text-ink">Création et gestion de compte<br><span class="font-normal text-slate-500">Ouverture d'accès PME ou cabinet, assistance à la récupération, vérification d'identité</span></td>
                                    <td class="py-4 pr-4 align-top">Nom, prénom, email, adresse postale, numéro de téléphone. Pour les cabinets&nbsp;: NINEA, RCCM, nom commercial</td>
                                    <td class="py-4 pr-4 align-top">Exécution du contrat</td>
                                    <td class="py-4 align-top">Durée des CGU + 5&nbsp;ans (prescription)</td>
                                </tr>
                                <tr>
                                    <td class="py-4 pr-4 align-top font-medium text-ink">Gestion de la relation prospects<br><span class="font-normal text-slate-500">Formulaire de contact, demande de rejoindre Fayeku Compta, newsletters, référencement</span></td>
                                    <td class="py-4 pr-4 align-top">Nom, prénom, email, téléphone, nom du cabinet, région, taille du portefeuille</td>
                                    <td class="py-4 pr-4 align-top">Intérêt légitime. Newsletter&nbsp;: consentement</td>
                                    <td class="py-4 align-top">3&nbsp;ans après le dernier contact. Newsletter&nbsp;: jusqu'au désabonnement</td>
                                </tr>
                                <tr>
                                    <td class="py-4 pr-4 align-top font-medium text-ink">Gestion de la relation clients et partenaires<br><span class="font-normal text-slate-500">Support, assistance, formulaires d'évaluation</span></td>
                                    <td class="py-4 pr-4 align-top">Nom, prénom, email, téléphone, données professionnelles, contenu des demandes</td>
                                    <td class="py-4 pr-4 align-top">Exécution du contrat</td>
                                    <td class="py-4 align-top">Durée des CGU</td>
                                </tr>
                                <tr>
                                    <td class="py-4 pr-4 align-top font-medium text-ink">Facturation et gestion des devis<br><span class="font-normal text-slate-500">Création, édition, envoi et suivi des factures et devis</span></td>
                                    <td class="py-4 pr-4 align-top">Données d'identification, données financières (montants, conditions de paiement), données des clients finaux de la PME</td>
                                    <td class="py-4 pr-4 align-top">Exécution du contrat</td>
                                    <td class="py-4 align-top">Durée des CGU. Factures&nbsp;: 10&nbsp;ans (obligation légale fiscale)</td>
                                </tr>
                                <tr>
                                    <td class="py-4 pr-4 align-top font-medium text-ink">Relances automatiques WhatsApp<br><span class="font-normal text-slate-500">Envoi de rappels de paiement aux clients de la PME via WhatsApp Business API</span></td>
                                    <td class="py-4 pr-4 align-top">Nom et numéro de téléphone WhatsApp du client final de la PME, montant et référence de la facture</td>
                                    <td class="py-4 pr-4 align-top">Exécution du contrat (sur instruction de la PME responsable de traitement)</td>
                                    <td class="py-4 align-top">Durée des CGU</td>
                                </tr>
                                <tr>
                                    <td class="py-4 pr-4 align-top font-medium text-ink">Pilotage de trésorerie<br><span class="font-normal text-slate-500">Visualisation et projection de la trésorerie à 90 jours</span></td>
                                    <td class="py-4 pr-4 align-top">Données financières de la PME (flux entrants/sortants, soldes), données de facturation</td>
                                    <td class="py-4 pr-4 align-top">Exécution du contrat</td>
                                    <td class="py-4 align-top">Durée des CGU</td>
                                </tr>
                                <tr>
                                    <td class="py-4 pr-4 align-top font-medium text-ink">Collaboration cabinet-PME<br><span class="font-normal text-slate-500">Accès multi-clients du cabinet, collecte de pièces, export comptable</span></td>
                                    <td class="py-4 pr-4 align-top">Données des PME clientes du cabinet (factures, pièces justificatives), données d'identification du cabinet</td>
                                    <td class="py-4 pr-4 align-top">Exécution du contrat</td>
                                    <td class="py-4 align-top">Durée des CGU. Pièces exportées&nbsp;: 10&nbsp;ans</td>
                                </tr>
                                <tr>
                                    <td class="py-4 pr-4 align-top font-medium text-ink">Commissions partenaires<br><span class="font-normal text-slate-500">Calcul et suivi des commissions des cabinets partenaires</span></td>
                                    <td class="py-4 pr-4 align-top">Données d'identification du cabinet, données de facturation des PME invitées, montants de commission</td>
                                    <td class="py-4 pr-4 align-top">Exécution du contrat</td>
                                    <td class="py-4 align-top">Durée des CGU + 10&nbsp;ans (obligation comptable)</td>
                                </tr>
                                <tr>
                                    <td class="py-4 pr-4 align-top font-medium text-ink">Facturation et gestion des paiements Fayeku<br><span class="font-normal text-slate-500">Facturation des abonnements, recouvrement</span></td>
                                    <td class="py-4 pr-4 align-top">Données d'identification, données financières, coordonnées de paiement (Mobile Money, virement), journaux de connexion</td>
                                    <td class="py-4 pr-4 align-top">Exécution du contrat. Facturation&nbsp;: obligation légale</td>
                                    <td class="py-4 align-top">10&nbsp;ans (obligation fiscale et comptable)</td>
                                </tr>
                                <tr>
                                    <td class="py-4 pr-4 align-top font-medium text-ink">Navigation sur le site et analyse d'audience<br><span class="font-normal text-slate-500">Cookies, traceurs analytiques</span></td>
                                    <td class="py-4 pr-4 align-top">Données de connexion (adresse IP, type d'appareil, pages visitées, durée de session)</td>
                                    <td class="py-4 pr-4 align-top">Consentement</td>
                                    <td class="py-4 align-top">13&nbsp;mois maximum</td>
                                </tr>
                                <tr>
                                    <td class="py-4 pr-4 align-top font-medium text-ink">Amélioration du service et statistiques<br><span class="font-normal text-slate-500">Analyse d'usage agrégée, amélioration des fonctionnalités (traitement secondaire)</span></td>
                                    <td class="py-4 pr-4 align-top">Journaux d'utilisation, événements analytiques, identifiants de session, catégorie d'activité, configuration de compte</td>
                                    <td class="py-4 pr-4 align-top">Intérêt légitime</td>
                                    <td class="py-4 align-top">Durée des CGU</td>
                                </tr>
                                <tr>
                                    <td class="py-4 pr-4 align-top font-medium text-ink">Sécurité de la plateforme<br><span class="font-normal text-slate-500">Authentification à deux facteurs, prévention de la fraude, gestion des incidents</span></td>
                                    <td class="py-4 pr-4 align-top">Journaux de connexion et d'audit, données de vérification d'identité</td>
                                    <td class="py-4 pr-4 align-top">Intérêt légitime</td>
                                    <td class="py-4 align-top">Durée des CGU</td>
                                </tr>
                                <tr>
                                    <td class="py-4 pr-4 align-top font-medium text-ink">Prospection commerciale<br><span class="font-normal text-slate-500">Communications personnalisées, publicité ciblée</span></td>
                                    <td class="py-4 pr-4 align-top">Données d'identification, données de connexion, catégorie d'activité</td>
                                    <td class="py-4 pr-4 align-top">Intérêt légitime pour les clients. Consentement pour les prospects</td>
                                    <td class="py-4 align-top">Durée des CGU pour les clients. 3&nbsp;ans après le dernier contact pour les prospects</td>
                                </tr>
                                <tr>
                                    <td class="py-4 pr-4 align-top font-medium text-ink">Respect des obligations légales et réglementaires<br><span class="font-normal text-slate-500">Réponse aux réquisitions judiciaires et administratives, contentieux</span></td>
                                    <td class="py-4 pr-4 align-top">Toutes données nécessaires à la réponse à la réquisition ou au contentieux</td>
                                    <td class="py-4 pr-4 align-top">Obligation légale</td>
                                    <td class="py-4 align-top">Durée strictement nécessaire au traitement de la réquisition ou du contentieux</td>
                                </tr>
                                <tr>
                                    <td class="py-4 pr-4 align-top font-medium text-ink">Gestion des demandes de droits<br><span class="font-normal text-slate-500">Traitement des demandes d'accès, rectification, effacement, opposition</span></td>
                                    <td class="py-4 pr-4 align-top">Données d'identification, pièce d'identité (si vérification nécessaire), objet de la demande</td>
                                    <td class="py-4 pr-4 align-top">Obligation légale</td>
                                    <td class="py-4 align-top">5&nbsp;ans à compter de la demande. Pièce d'identité&nbsp;: durée strictement nécessaire à la vérification</td>
                                </tr>
                            </tbody>
                        </table>
                    </div>
                </article>

            </div>

            <p class="text-center text-sm text-slate-500">&copy; {{ now()->year }} {{ $site['name'] }} — Édité par {{ $site['legal']['editor'] }} ({{ $site['legal']['company'] }}).</p>
        </div>
    </section>
</x-layouts.marketing>
