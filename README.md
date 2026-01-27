# PB Richieste Frequenza

Plugin WordPress personalizzato per la gestione delle **richieste di frequenza** da parte dei genitori, con generazione automatica di **DOCX e PDF** a partire da template Word.

Il plugin consente:
- creazione di moduli personalizzati
- compilazione guidata per i genitori
- generazione documenti PDF pronti per l’invio
- invio PDF via email
- gestione sedi con indirizzo completo
- aggiornamento diretto da GitHub (Git Updater)

---

## Funzionalità principali

- 📄 **Template DOCX** con placeholder (`${campo}`)
- 📄 **Generazione PDF** tramite LibreOffice headless
- 🧩 **Moduli configurabili** da interfaccia WordPress
- 👶 Dati bambino (nome, data nascita, codice fiscale)
- 🏫 **Sede di frequenza** selezionabile (dropdown → indirizzo completo nel PDF)
- 📧 **Invio PDF via email** al genitore
- 🔄 Aggiornamenti da GitHub con branch selezionabile

---

## Requisiti

- WordPress 6.x
- PHP ≥ 8.0
- LibreOffice installato sul server (`soffice`)
- Permessi di scrittura su `wp-content/uploads`

---

## Installazione

### Metodo consigliato (Git Updater)
1. Installa il plugin **Git Updater**
2. Carica questo plugin da ZIP oppure collega il repository GitHub
3. Attiva il plugin da WordPress

### Metodo manuale
1. Copia la cartella `pb-richieste-frequenza` in:
