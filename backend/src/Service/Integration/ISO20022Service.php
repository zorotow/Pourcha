<?php

namespace App\Service\Integration;

use App\Entity\Invoice;
use App\Entity\Requisition;
use App\Entity\Supplier;

/**
 * ISO 20022 Payment Messaging Service
 *
 * Generates payment messages in ISO 20022 format (pain.001 for payment initiation).
 * Used for SEPA, wire transfers, and international payments.
 */
class ISO20022Service
{
    /**
     * Generate pain.001.001.03 - Customer Credit Transfer Initiation
     */
    public function generatePaymentInitiation(
        Invoice $invoice,
        array $debtorAccount,
        array $creditorAccount,
        string $messageId = null
    ): string {
        $messageId = $messageId ?? $this->generateMessageId();
        $creationDateTime = new \DateTime();

        $supplier = $invoice->getSupplier();

        $xml = new \DOMDocument('1.0', 'UTF-8');
        $xml->formatOutput = true;

        // Root element
        $document = $xml->createElement('Document');
        $document->setAttribute('xmlns', 'urn:iso:std:iso:20022:tech:xsd:pain.001.001.03');
        $document->setAttribute('xmlns:xsi', 'http://www.w3.org/2001/XMLSchema-instance');
        $xml->appendChild($document);

        // Customer Credit Transfer Initiation
        $cstmrCdtTrfInitn = $xml->createElement('CstmrCdtTrfInitn');
        $document->appendChild($cstmrCdtTrfInitn);

        // Group Header
        $grpHdr = $xml->createElement('GrpHdr');
        $cstmrCdtTrfInitn->appendChild($grpHdr);

        $grpHdr->appendChild($xml->createElement('MsgId', $messageId));
        $grpHdr->appendChild($xml->createElement('CreDtTm', $creationDateTime->format('Y-m-d\TH:i:s')));
        $grpHdr->appendChild($xml->createElement('NbOfTxs', '1')); // Number of transactions
        $grpHdr->appendChild($xml->createElement('CtrlSum', $invoice->getAmount())); // Total amount

        // Initiating Party
        $initgPty = $xml->createElement('InitgPty');
        $grpHdr->appendChild($initgPty);
        $initgPty->appendChild($xml->createElement('Nm', $debtorAccount['name']));

        // Payment Information
        $pmtInf = $xml->createElement('PmtInf');
        $cstmrCdtTrfInitn->appendChild($pmtInf);

        $pmtInf->appendChild($xml->createElement('PmtInfId', 'PMT-' . $invoice->getInvoiceNumber()));
        $pmtInf->appendChild($xml->createElement('PmtMtd', 'TRF')); // Transfer
        $pmtInf->appendChild($xml->createElement('BtchBookg', 'false'));
        $pmtInf->appendChild($xml->createElement('NbOfTxs', '1'));
        $pmtInf->appendChild($xml->createElement('CtrlSum', $invoice->getAmount()));

        // Payment Type Information
        $pmtTpInf = $xml->createElement('PmtTpInf');
        $pmtInf->appendChild($pmtTpInf);

        $svcLvl = $xml->createElement('SvcLvl');
        $pmtTpInf->appendChild($svcLvl);
        $svcLvl->appendChild($xml->createElement('Cd', 'SEPA')); // SEPA or URGP for urgent

        // Requested Execution Date
        $reqExctnDt = $invoice->getDueDate() ?? new \DateTimeImmutable();
        $pmtInf->appendChild($xml->createElement('ReqdExctnDt', $reqExctnDt->format('Y-m-d')));

        // Debtor (Payer) Information
        $dbtr = $xml->createElement('Dbtr');
        $pmtInf->appendChild($dbtr);
        $dbtr->appendChild($xml->createElement('Nm', $debtorAccount['name']));

        // Debtor Account
        $dbtrAcct = $xml->createElement('DbtrAcct');
        $pmtInf->appendChild($dbtrAcct);

        $dbtrId = $xml->createElement('Id');
        $dbtrAcct->appendChild($dbtrId);
        $dbtrIBAN = $xml->createElement('IBAN', $debtorAccount['iban'] ?? '');
        $dbtrId->appendChild($dbtrIBAN);

        // Debtor Agent (Bank)
        $dbtrAgt = $xml->createElement('DbtrAgt');
        $pmtInf->appendChild($dbtrAgt);

        $finInstnId = $xml->createElement('FinInstnId');
        $dbtrAgt->appendChild($finInstnId);
        $finInstnId->appendChild($xml->createElement('BIC', $debtorAccount['bic'] ?? ''));

        // Credit Transfer Transaction Information
        $cdtTrfTxInf = $xml->createElement('CdtTrfTxInf');
        $pmtInf->appendChild($cdtTrfTxInf);

        // Payment ID
        $pmtId = $xml->createElement('PmtId');
        $cdtTrfTxInf->appendChild($pmtId);
        $pmtId->appendChild($xml->createElement('EndToEndId', 'INV-' . $invoice->getInvoiceNumber()));

        // Amount
        $amt = $xml->createElement('Amt');
        $cdtTrfTxInf->appendChild($amt);

        $instdAmt = $xml->createElement('InstdAmt', $invoice->getAmount());
        $instdAmt->setAttribute('Ccy', $invoice->getCurrency());
        $amt->appendChild($instdAmt);

        // Creditor Agent (Supplier's Bank)
        $cdtrAgt = $xml->createElement('CdtrAgt');
        $cdtTrfTxInf->appendChild($cdtrAgt);

        $cdtrFinInstnId = $xml->createElement('FinInstnId');
        $cdtrAgt->appendChild($cdtrFinInstnId);
        $cdtrFinInstnId->appendChild($xml->createElement('BIC', $creditorAccount['bic'] ?? ''));

        // Creditor (Supplier) Information
        $cdtr = $xml->createElement('Cdtr');
        $cdtTrfTxInf->appendChild($cdtr);
        $cdtr->appendChild($xml->createElement('Nm', $supplier->getName()));

        // Creditor Account
        $cdtrAcct = $xml->createElement('CdtrAcct');
        $cdtTrfTxInf->appendChild($cdtrAcct);

        $cdtrAcctId = $xml->createElement('Id');
        $cdtrAcct->appendChild($cdtrAcctId);
        $cdtrAcctId->appendChild($xml->createElement('IBAN', $creditorAccount['iban'] ?? ''));

        // Remittance Information
        $rmtInf = $xml->createElement('RmtInf');
        $cdtTrfTxInf->appendChild($rmtInf);

        $remittanceText = sprintf(
            'Invoice %s - %s',
            $invoice->getInvoiceNumber(),
            $invoice->getDescription() ?? ''
        );
        $rmtInf->appendChild($xml->createElement('Ustrd', substr($remittanceText, 0, 140)));

        return $xml->saveXML();
    }

    /**
     * Generate pain.002.001.03 - Payment Status Report
     */
    public function generatePaymentStatusReport(
        string $originalMessageId,
        string $status, // 'ACCP' (Accepted), 'RJCT' (Rejected), 'PDNG' (Pending)
        string $statusReason = null
    ): string {
        $xml = new \DOMDocument('1.0', 'UTF-8');
        $xml->formatOutput = true;

        // Root element
        $document = $xml->createElement('Document');
        $document->setAttribute('xmlns', 'urn:iso:std:iso:20022:tech:xsd:pain.002.001.03');
        $xml->appendChild($document);

        // Payment Status Report
        $pmtStsRpt = $xml->createElement('CstmrPmtStsRpt');
        $document->appendChild($pmtStsRpt);

        // Group Header
        $grpHdr = $xml->createElement('GrpHdr');
        $pmtStsRpt->appendChild($grpHdr);

        $grpHdr->appendChild($xml->createElement('MsgId', $this->generateMessageId()));
        $grpHdr->appendChild($xml->createElement('CreDtTm', (new \DateTime())->format('Y-m-d\TH:i:s')));

        // Original Group Information
        $orgnlGrpInfAndSts = $xml->createElement('OrgnlGrpInfAndSts');
        $pmtStsRpt->appendChild($orgnlGrpInfAndSts);

        $orgnlGrpInfAndSts->appendChild($xml->createElement('OrgnlMsgId', $originalMessageId));
        $orgnlGrpInfAndSts->appendChild($xml->createElement('OrgnlMsgNmId', 'pain.001.001.03'));
        $orgnlGrpInfAndSts->appendChild($xml->createElement('GrpSts', $status));

        if ($statusReason) {
            $stsRsnInf = $xml->createElement('StsRsnInf');
            $orgnlGrpInfAndSts->appendChild($stsRsnInf);

            $rsn = $xml->createElement('Rsn');
            $stsRsnInf->appendChild($rsn);
            $rsn->appendChild($xml->createElement('Cd', $statusReason));
        }

        return $xml->saveXML();
    }

    /**
     * Generate camt.053.001.02 - Bank to Customer Statement
     * (Used for transaction reconciliation)
     */
    public function generateBankStatement(array $transactions, array $accountInfo): string
    {
        $xml = new \DOMDocument('1.0', 'UTF-8');
        $xml->formatOutput = true;

        // Root element
        $document = $xml->createElement('Document');
        $document->setAttribute('xmlns', 'urn:iso:std:iso:20022:tech:xsd:camt.053.001.02');
        $xml->appendChild($document);

        // Bank to Customer Statement
        $bkToCstmrStmt = $xml->createElement('BkToCstmrStmt');
        $document->appendChild($bkToCstmrStmt);

        // Group Header
        $grpHdr = $xml->createElement('GrpHdr');
        $bkToCstmrStmt->appendChild($grpHdr);

        $grpHdr->appendChild($xml->createElement('MsgId', $this->generateMessageId()));
        $grpHdr->appendChild($xml->createElement('CreDtTm', (new \DateTime())->format('Y-m-d\TH:i:s')));

        // Statement
        $stmt = $xml->createElement('Stmt');
        $bkToCstmrStmt->appendChild($stmt);

        $stmt->appendChild($xml->createElement('Id', 'STMT-' . date('YmdHis')));
        $stmt->appendChild($xml->createElement('CreDtTm', (new \DateTime())->format('Y-m-d\TH:i:s')));

        // Account
        $acct = $xml->createElement('Acct');
        $stmt->appendChild($acct);

        $acctId = $xml->createElement('Id');
        $acct->appendChild($acctId);
        $acctId->appendChild($xml->createElement('IBAN', $accountInfo['iban']));

        // Balance information
        $bal = $xml->createElement('Bal');
        $stmt->appendChild($bal);

        $tp = $xml->createElement('Tp');
        $bal->appendChild($tp);

        $cdOrPrtry = $xml->createElement('CdOrPrtry');
        $tp->appendChild($cdOrPrtry);
        $cdOrPrtry->appendChild($xml->createElement('Cd', 'CLBD')); // Closing booked

        $balAmt = $xml->createElement('Amt', $accountInfo['balance']);
        $balAmt->setAttribute('Ccy', $accountInfo['currency']);
        $bal->appendChild($balAmt);

        $bal->appendChild($xml->createElement('CdtDbtInd', $accountInfo['balance'] >= 0 ? 'CRDT' : 'DBIT'));
        $bal->appendChild($xml->createElement('Dt', (new \DateTime())->format('Y-m-d')));

        return $xml->saveXML();
    }

    /**
     * Parse incoming ISO 20022 payment message
     */
    public function parsePaymentMessage(string $xmlContent): array
    {
        $xml = new \DOMDocument();
        $xml->loadXML($xmlContent);

        $xpath = new \DOMXPath($xml);

        // Register namespaces
        $xpath->registerNamespace('pain', 'urn:iso:std:iso:20022:tech:xsd:pain.001.001.03');

        // Extract message data
        $messageId = $xpath->query('//pain:GrpHdr/pain:MsgId')->item(0)?->nodeValue;
        $amount = $xpath->query('//pain:PmtInf/pain:CtrlSum')->item(0)?->nodeValue;
        $currency = $xpath->query('//pain:InstdAmt/@Ccy')->item(0)?->nodeValue;

        return [
            'message_id' => $messageId,
            'amount' => $amount,
            'currency' => $currency,
            // Add more fields as needed
        ];
    }

    /**
     * Generate unique message ID
     */
    private function generateMessageId(): string
    {
        return 'POURCHA-' . date('YmdHis') . '-' . bin2hex(random_bytes(4));
    }

    /**
     * Validate IBAN
     */
    public function validateIBAN(string $iban): bool
    {
        // Remove spaces
        $iban = str_replace(' ', '', $iban);

        // Check length (15-34 characters)
        if (strlen($iban) < 15 || strlen($iban) > 34) {
            return false;
        }

        // Move first 4 characters to end
        $rearranged = substr($iban, 4) . substr($iban, 0, 4);

        // Replace letters with numbers (A=10, B=11, ..., Z=35)
        $numeric = '';
        for ($i = 0; $i < strlen($rearranged); $i++) {
            $char = $rearranged[$i];
            if (ctype_alpha($char)) {
                $numeric .= (ord(strtoupper($char)) - 55);
            } else {
                $numeric .= $char;
            }
        }

        // Calculate mod 97
        return bcmod($numeric, '97') === '1';
    }
}
