/**
 * Platform console "Integrations" (mail + SMS credentials) catalog: en / fr / ar built from one row list so the three
 * locales can never drift apart. Row = [key, en, fr, ar]. Merged into appCatalog.js.
 */
const rows = [
    ['appNav.integrations', 'Integrations', 'Intégrations', 'التكاملات'],

    // ---- page
    ['integ.title', 'Integrations', 'Intégrations', 'التكاملات'],
    ['integ.subtitle', 'Outgoing e-mail and SMS for the whole platform. Schools never see or hold these credentials.', 'E-mail et SMS sortants pour toute la plateforme. Les écoles ne voient ni ne détiennent ces identifiants.', 'البريد الإلكتروني والرسائل النصية الصادرة للمنصة بأكملها. لا ترى المدارس هذه البيانات ولا تحتفظ بها.'],
    ['integ.scope.title', 'One set for every school', 'Un seul jeu pour toutes les écoles', 'مجموعة واحدة لكل المدارس'],
    ['integ.scope.body', 'These settings are shared by the whole platform. Each school only chooses whether to use SMS and how much; its messages go out through the account configured here.', 'Ces paramètres sont partagés par toute la plateforme. Chaque école choisit seulement d’utiliser ou non les SMS et en quelle quantité ; ses messages partent via le compte configuré ici.', 'هذه الإعدادات مشتركة بين المنصة كلها. تختار كل مدرسة فقط هل تستخدم الرسائل النصية وبأي كمية، وتخرج رسائلها عبر الحساب المضبوط هنا.'],
    ['integ.flow.title', 'How it works', 'Comment cela fonctionne', 'كيف يعمل'],
    ['integ.flow.1', '1. Save the settings. Nothing changes yet.', '1. Enregistrez les paramètres. Rien ne change encore.', '1. احفظ الإعدادات. لا يتغير شيء بعد.'],
    ['integ.flow.2', '2. Send a test message to yourself.', '2. Envoyez-vous un message de test.', '2. أرسل رسالة اختبار إلى نفسك.'],
    ['integ.flow.3', '3. Switch on. Only tested settings can go live.', '3. Activez. Seuls les paramètres testés peuvent passer en production.', '3. فعّل. لا تُعتمد إلا الإعدادات التي نجح اختبارها.'],
    ['integ.flow.4', '4. Confirm the message really arrived. Only then is it marked verified.', '4. Confirmez que le message est bien arrivé. C’est seulement alors qu’il est marqué vérifié.', '4. أكّد وصول الرسالة فعلاً. عندها فقط تُعلَّم كمُتحقَّق منها.'],
    ['integ.secretsNote', 'Secrets are encrypted, never shown again after saving, and every change needs your password and is logged.', 'Les secrets sont chiffrés, jamais réaffichés après l’enregistrement, et chaque modification exige votre mot de passe et est journalisée.', 'الأسرار مشفّرة ولا تُعرض مجدداً بعد الحفظ، وكل تغيير يتطلب كلمة مرورك ويُسجَّل.'],

    // ---- cards
    ['integ.name.mail', 'E-mail (SMTP)', 'E-mail (SMTP)', 'البريد الإلكتروني (SMTP)'],
    ['integ.name.sms', 'SMS', 'SMS', 'الرسائل النصية'],
    ['integ.state.none', 'Not set up', 'Non configuré', 'غير مُعدّ'],
    ['integ.state.unreadable', 'Unreadable, enter again', 'Illisible, à saisir à nouveau', 'غير مقروء، أعد الإدخال'],
    ['integ.state.untested', 'Saved, not tested', 'Enregistré, non testé', 'محفوظ، لم يُختبر'],
    ['integ.state.failed', 'Test failed', 'Test échoué', 'فشل الاختبار'],
    ['integ.state.tested', 'Test accepted', 'Test accepté', 'قُبل الاختبار'],
    ['integ.active', 'In use', 'En service', 'قيد الاستخدام'],
    ['integ.inactive', 'Not in use', 'Hors service', 'غير مستخدم'],
    ['integ.verified', 'Verified', 'Vérifié', 'مُتحقَّق منه'],
    ['integ.unverified', 'Not verified', 'Non vérifié', 'غير مُتحقَّق منه'],
    ['integ.using.server', 'Currently using the server default.', 'Utilise actuellement la configuration du serveur.', 'يستخدم حالياً إعداد الخادم الافتراضي.'],
    ['integ.using.console', 'Currently using the settings below.', 'Utilise actuellement les paramètres ci-dessous.', 'يستخدم حالياً الإعدادات أدناه.'],
    ['integ.server.label', 'Server default (.env)', 'Configuration du serveur (.env)', 'الإعداد الافتراضي للخادم (.env)'],
    ['integ.server.logOnly', 'Nothing is delivered: messages are only written to a log.', 'Rien n’est livré : les messages sont seulement écrits dans un journal.', 'لا يتم تسليم شيء: تُكتب الرسائل في سجل فقط.'],
    ['integ.server.real', 'Delivers for real.', 'Livre réellement.', 'يسلّم فعلياً.'],
    ['integ.driver.smtp', 'SMTP', 'SMTP', 'SMTP'],
    ['integ.driver.log', 'Log only', 'Journal seulement', 'السجل فقط'],
    ['integ.driver.array', 'Memory only', 'Mémoire seulement', 'الذاكرة فقط'],
    ['integ.driver.null', 'Off', 'Désactivé', 'متوقف'],
    ['integ.driver.twilio', 'Twilio', 'Twilio', 'Twilio'],
    ['integ.driver.africastalking', 'Africa’s Talking', 'Africa’s Talking', 'Africa’s Talking'],

    // ---- fields
    ['integ.f.host', 'SMTP host', 'Hôte SMTP', 'مضيف SMTP'],
    ['integ.f.port', 'Port', 'Port', 'المنفذ'],
    ['integ.f.encryption', 'Encryption', 'Chiffrement', 'التشفير'],
    ['integ.f.username', 'Username', 'Nom d’utilisateur', 'اسم المستخدم'],
    ['integ.f.password', 'Password', 'Mot de passe', 'كلمة المرور'],
    ['integ.f.from_address', 'From address', 'Adresse d’expédition', 'عنوان المرسل'],
    ['integ.f.from_name', 'From name', 'Nom de l’expéditeur', 'اسم المرسل'],
    ['integ.f.driver', 'Provider', 'Fournisseur', 'المزوّد'],
    ['integ.f.twilio_sid', 'Account SID', 'SID du compte', 'معرّف الحساب (SID)'],
    ['integ.f.twilio_token', 'Auth token', 'Jeton d’authentification', 'رمز المصادقة'],
    ['integ.f.twilio_from', 'From number', 'Numéro d’expédition', 'رقم المرسل'],
    ['integ.f.twilio_messaging_service_sid', 'Messaging service SID', 'SID du service de messagerie', 'معرّف خدمة المراسلة'],
    ['integ.f.twilio_status_callback', 'Status callback URL', 'URL de rappel d’état', 'رابط استدعاء الحالة'],
    ['integ.f.at_username', 'Username', 'Nom d’utilisateur', 'اسم المستخدم'],
    ['integ.f.at_api_key', 'API key', 'Clé API', 'مفتاح API'],
    ['integ.f.at_sender_id', 'Sender ID', 'Identifiant d’expéditeur', 'معرّف المرسل'],
    ['integ.f.at_sandbox', 'Sandbox mode', 'Mode bac à sable', 'وضع الاختبار (Sandbox)'],
    ['integ.f.at_webhook_secret', 'Webhook path secret', 'Secret du chemin webhook', 'سر مسار الويب هوك'],
    ['integ.enc.tls', 'STARTTLS (usually port 587)', 'STARTTLS (en général le port 587)', 'STARTTLS (عادةً المنفذ 587)'],
    ['integ.enc.ssl', 'SSL/TLS (usually port 465)', 'SSL/TLS (en général le port 465)', 'SSL/TLS (عادةً المنفذ 465)'],
    ['integ.secret.empty', 'Not set', 'Non défini', 'غير محدد'],
    ['integ.secret.keep', 'Stored. Leave blank to keep it.', 'Enregistré. Laissez vide pour le conserver.', 'محفوظ. اتركه فارغاً للإبقاء عليه.'],
    ['integ.secret.keepHint', 'Stored, ends with {hint}. Leave blank to keep it.', 'Enregistré, se termine par {hint}. Laissez vide pour le conserver.', 'محفوظ وينتهي بـ {hint}. اتركه فارغاً للإبقاء عليه.'],
    ['integ.hint.twilioFrom', 'Give a sender number or a messaging service SID.', 'Indiquez un numéro d’expédition ou un SID de service de messagerie.', 'أدخل رقم مرسل أو معرّف خدمة مراسلة.'],
    ['integ.hint.atWebhook', 'Letters, numbers, hyphen and underscore only. It becomes part of the delivery-report URL.', 'Lettres, chiffres, tiret et tiret bas uniquement. Il fait partie de l’URL des rapports de livraison.', 'أحرف وأرقام وشرطة وشرطة سفلية فقط. يصبح جزءاً من رابط تقارير التسليم.'],
    ['integ.webhook.title', 'Delivery-report URL', 'URL des rapports de livraison', 'رابط تقارير التسليم'],
    ['integ.webhook.note', 'Enter this in the provider dashboard so delivery reports reach SchoolOS.', 'Saisissez-la dans le tableau de bord du fournisseur pour que les rapports de livraison parviennent à SchoolOS.', 'أدخله في لوحة المزوّد لتصل تقارير التسليم إلى SchoolOS.'],

    // ---- actions
    ['integ.password', 'Your password', 'Votre mot de passe', 'كلمة مرورك'],
    ['integ.password.hint', 'Needed to save, switch on or off, or remove.', 'Requis pour enregistrer, activer, désactiver ou supprimer.', 'مطلوبة للحفظ أو التفعيل أو الإيقاف أو الحذف.'],
    ['integ.save', 'Save settings', 'Enregistrer', 'حفظ الإعدادات'],
    ['integ.remove', 'Remove saved settings', 'Supprimer les paramètres', 'حذف الإعدادات المحفوظة'],
    ['integ.confirm.remove', 'Remove the saved settings? The server default will be used again.', 'Supprimer les paramètres enregistrés ? La configuration du serveur sera de nouveau utilisée.', 'حذف الإعدادات المحفوظة؟ سيُستخدم إعداد الخادم الافتراضي من جديد.'],
    ['integ.edit.note', 'Changing a value while in use switches it off until it has been tested again.', 'Modifier une valeur en service la désactive jusqu’à un nouveau test.', 'تغيير قيمة أثناء الاستخدام يوقفها إلى أن تُختبر من جديد.'],
    ['integ.unreadable.note', 'The stored secrets cannot be read (the application key changed). Enter them again.', 'Les secrets enregistrés sont illisibles (la clé de l’application a changé). Saisissez-les à nouveau.', 'تعذّرت قراءة الأسرار المحفوظة (تغيّر مفتاح التطبيق). أعد إدخالها.'],
    ['integ.test.title', 'Send a test', 'Envoyer un test', 'إرسال اختبار'],
    ['integ.test.email', 'Send to e-mail address', 'Envoyer à l’adresse e-mail', 'أرسل إلى عنوان البريد'],
    ['integ.test.phone', 'Send to phone number', 'Envoyer au numéro de téléphone', 'أرسل إلى رقم الهاتف'],
    ['integ.test.sendMail', 'Send test email', 'Envoyer un e-mail test', 'إرسال بريد اختبار'],
    ['integ.test.sendSms', 'Send test SMS', 'Envoyer un SMS test', 'إرسال رسالة اختبار'],
    ['integ.test.lastOk', 'Accepted {when} for {to}. Accepted is not the same as received: check the inbox or phone.', 'Accepté {when} pour {to}. Accepté ne veut pas dire reçu : vérifiez la boîte ou le téléphone.', 'قُبلت {when} إلى {to}. القبول لا يعني الوصول: تحقق من البريد أو الهاتف.'],
    ['integ.test.lastFailed', 'Failed {when}. Reason:', 'Échec {when}. Motif :', 'فشلت {when}. السبب:'],
    ['integ.test.needed', 'Send a successful test of these exact settings to be able to switch on.', 'Envoyez un test réussi de ces paramètres exacts pour pouvoir activer.', 'أرسل اختباراً ناجحاً لهذه الإعدادات بالذات لتتمكن من التفعيل.'],
    ['integ.on', 'Switch on', 'Activer', 'تفعيل'],
    ['integ.off', 'Switch off', 'Désactiver', 'إيقاف'],
    ['integ.confirmReceived', 'I received it', 'Je l’ai reçu', 'استلمتها'],
    ['integ.confirmedAt', 'Confirmed received {when}.', 'Réception confirmée {when}.', 'تم تأكيد الاستلام {when}.'],
    ['integ.savedBy', 'Last saved {when} by {name}.', 'Dernier enregistrement {when} par {name}.', 'آخر حفظ {when} بواسطة {name}.'],

    // ---- activity
    ['integ.activity.title', 'Recent changes', 'Modifications récentes', 'آخر التغييرات'],
    ['integ.activity.empty', 'No changes yet.', 'Aucune modification pour l’instant.', 'لا توجد تغييرات بعد.'],
    ['integ.activity.by', 'by {name}', 'par {name}', 'بواسطة {name}'],
    ['integ.event.saved', 'Saved settings', 'Paramètres enregistrés', 'حُفظت الإعدادات'],
    ['integ.event.test_ok', 'Test accepted', 'Test accepté', 'قُبل الاختبار'],
    ['integ.event.test_failed', 'Test failed', 'Test échoué', 'فشل الاختبار'],
    ['integ.event.activated', 'Switched on', 'Activé', 'تم التفعيل'],
    ['integ.event.deactivated', 'Switched off', 'Désactivé', 'تم الإيقاف'],
    ['integ.event.cleared', 'Removed saved settings', 'Paramètres supprimés', 'حُذفت الإعدادات المحفوظة'],
    ['integ.event.confirmed', 'Confirmed received', 'Réception confirmée', 'تم تأكيد الاستلام'],

    // ---- messages (flash and validation codes)
    ['ok.integ.saved', 'Settings saved. Nothing changes until you test and switch them on.', 'Paramètres enregistrés. Rien ne change tant que vous ne les avez pas testés et activés.', 'تم حفظ الإعدادات. لا يتغير شيء حتى تختبرها وتفعّلها.'],
    ['ok.integ.savedOff', 'Settings saved. They were switched off because a value changed: test and switch on again.', 'Paramètres enregistrés. Ils ont été désactivés car une valeur a changé : testez puis réactivez.', 'تم حفظ الإعدادات. أُوقفت لأن قيمة تغيّرت: اختبرها وفعّلها من جديد.'],
    ['ok.integ.activated', 'Switched on.', 'Activé.', 'تم التفعيل.'],
    ['ok.integ.deactivated', 'Switched off. The server default is used again.', 'Désactivé. La configuration du serveur est de nouveau utilisée.', 'تم الإيقاف. عاد استخدام إعداد الخادم الافتراضي.'],
    ['ok.integ.cleared', 'Saved settings removed.', 'Paramètres enregistrés supprimés.', 'حُذفت الإعدادات المحفوظة.'],
    ['ok.integ.confirmed', 'Marked as verified.', 'Marqué comme vérifié.', 'تم وضع علامة مُتحقَّق منه.'],
    ['ok.integ.testSentMail', 'Test e-mail accepted by the mail server. Check the inbox, then confirm.', 'E-mail test accepté par le serveur de messagerie. Vérifiez la boîte de réception, puis confirmez.', 'قبِل خادم البريد رسالة الاختبار. تحقق من صندوق الوارد ثم أكّد.'],
    ['ok.integ.testSentSms', 'Test SMS accepted by the provider. Check the phone, then confirm.', 'SMS test accepté par le fournisseur. Vérifiez le téléphone, puis confirmez.', 'قبِل المزوّد رسالة الاختبار. تحقق من الهاتف ثم أكّد.'],
    ['err.integ.required', 'This field is required.', 'Ce champ est obligatoire.', 'هذا الحقل مطلوب.'],
    ['err.integ.invalid', 'This value is not valid.', 'Cette valeur n’est pas valide.', 'هذه القيمة غير صالحة.'],
    ['err.integ.tooLong', 'This value is too long.', 'Cette valeur est trop longue.', 'هذه القيمة طويلة جداً.'],
    ['err.integ.email', 'Enter a valid e-mail address.', 'Saisissez une adresse e-mail valide.', 'أدخل عنوان بريد إلكتروني صالحاً.'],
    ['err.integ.port', 'Enter a port between 1 and 65535.', 'Saisissez un port entre 1 et 65535.', 'أدخل منفذاً بين 1 و65535.'],
    ['err.integ.url', 'Enter a full URL starting with https://.', 'Saisissez une URL complète commençant par https://.', 'أدخل رابطاً كاملاً يبدأ بـ https://.'],
    ['err.integ.phone', 'Enter a valid phone number.', 'Saisissez un numéro de téléphone valide.', 'أدخل رقم هاتف صالحاً.'],
    ['err.integ.password', 'Your password is not correct.', 'Votre mot de passe est incorrect.', 'كلمة مرورك غير صحيحة.'],
    ['err.integ.testFailed', 'The test failed. The reason is shown on the card.', 'Le test a échoué. Le motif est affiché sur la carte.', 'فشل الاختبار. يظهر السبب على البطاقة.'],
    ['err.integ.testFirst', 'Send a successful test of these exact settings first.', 'Envoyez d’abord un test réussi de ces paramètres exacts.', 'أرسل أولاً اختباراً ناجحاً لهذه الإعدادات بالذات.'],
    ['err.integ.nothingSaved', 'Save the settings first.', 'Enregistrez d’abord les paramètres.', 'احفظ الإعدادات أولاً.'],
];

const en = {};
const fr = {};
const ar = {};

for (const [key, e, f, a] of rows) {
    if (key in en) {
        throw new Error(`Duplicate integrations catalog key: ${key}`);
    }
    en[key] = e;
    fr[key] = f;
    ar[key] = a;
}

export const integrationsCatalog = { en, fr, ar };
