# In disparte — migrazione Aruba
Bozza tecnica PHP, non ancora collaudata su Aruba. Non unire a main.
1. Scaricare lo ZIP di questo ramo e caricare i contenuti nella radice web di indisparte.it. Escludere server.js, package.json e file Git. Conservare .htaccess e privato/.htaccess.
2. Attivare HTTPS e PHP 8.3/8.4. Verificare che /privato/ restituisca 403 e che PHP possa scrivere nella radice e assets.
3. Generare in locale un hash con PHP: password_hash(password personale, PASSWORD_DEFAULT). Scrivere esclusivamente l'hash in privato/password.hash, tramite FTP sicuro. Non inserire password o hash nel repository.
4. Aprire https://indisparte.it/editor. Accedere. Verificare modifica, annullamento, aggiunta riquadri, caricamento fotografie, nuova pagina, controllo link, pubblicazione e ripristino.
5. Verificare dall'esterno: accesso API anonimo 401, password hash non leggibile, tutte le foto e 16 tappe guidate, no richieste GitHub/Railway.
6. Solo dopo collaudo dismettere Railway. Le bozze locali nel vecchio browser devono essere esportate prima del passaggio e riconciliate con la nuova versione.
Limiti: link esterni segnalati per controllo manuale; nessuna verifica di recapito email/telefono; pubblicazione multi-file con backup e rollback, non transazione atomica globale. Non è ancora una consegna definitiva.
