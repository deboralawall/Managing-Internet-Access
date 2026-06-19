package br.udesc.gerencia;

import org.snmp4j.CommunityTarget;
import org.snmp4j.PDU;
import org.snmp4j.Snmp;
import org.snmp4j.TransportMapping;
import org.snmp4j.event.ResponseEvent;
import org.snmp4j.mp.SnmpConstants;
import org.snmp4j.smi.*;
import org.snmp4j.transport.DefaultUdpTransportMapping;

/**
 * Classe principal que executa operações SNMP SET para bloqueio/desbloqueio de portas
 * Utiliza a biblioteca SNMP4J para comunicação com switches de rede
 */
public class GerenteSNMP {

    private static final int SNMP_PORT = 161;
    private static final int SNMP_TIMEOUT = 3000; // 3 segundos
    private static final int SNMP_RETRIES = 0;

    public static void main(String[] args) {
        if (args.length < 4) {
            System.err.println("Uso: java GerenteSNMP <ip_switch> <comunidade> <porta_switch> <acao>");
            System.err.println("  ip_switch: IP do switch (ex: 192.168.1.10)");
            System.err.println("  comunidade: Comunidade SNMP (ex: public)");
            System.err.println("  porta_switch: Número da porta do switch (ex: 1, 2, 3, ...)");
            System.err.println("  acao: 'bloquear' ou 'desbloquear'");
            System.exit(1);
        }

        String ipSwitch = args[0];
        String comunidade = args[1];
        int portaSwitch = Integer.parseInt(args[2]);
        String acao = args[3].toLowerCase();

        try {
            executarSNMP(ipSwitch, comunidade, portaSwitch, acao);
            System.out.println("Operação SNMP executada com sucesso!");
        } catch (Exception e) {
            System.err.println("Erro ao executar operação SNMP: " + e.getMessage());
            e.printStackTrace();
            System.exit(1);
        }
    }

    /**
     * Executa operação SNMP SET para controlar porta do switch
     */
    public static void executarSNMP(String ipSwitch, String comunidade, 
                                    int portaSwitch, String acao) throws Exception {
        
        Snmp snmp = null;
        try {
            // Configurar transport mapping
            TransportMapping<?> transport = new DefaultUdpTransportMapping();
            snmp = new Snmp(transport);
            transport.listen();

            // Configurar target (alvo SNMP)
            CommunityTarget target = new CommunityTarget();
            target.setCommunity(new OctetString(comunidade));
            target.setVersion(SnmpConstants.version2c);
            target.setAddress(new UdpAddress(ipSwitch + "/" + SNMP_PORT));
            target.setRetries(SNMP_RETRIES);
            target.setTimeout(SNMP_TIMEOUT);

            // Criar PDU
            PDU pdu = new PDU();
            pdu.setType(PDU.SET);

            // OID para porta (exemplo: 1.3.6.1.2.1.17.7.1.4.3.1 = ifAdminStatus)
            // Ajuste o OID conforme o switch
            String oid = "1.3.6.1.2.1.2.2.1.7." + portaSwitch;
            
            // Valor: 1 = up (desbloquear), 2 = down (bloquear)
            int valor = "bloquear".equals(acao) ? 2 : 1;
            
            pdu.add(new VariableBinding(new OID(oid), new Integer32(valor)));

            // Enviar SET
            System.out.println("Enviando comando SNMP SET para " + ipSwitch + ":" + SNMP_PORT);
            System.out.println("  Ação: " + acao + " (valor SNMP: " + valor + ")");
            System.out.println("  Porta: " + portaSwitch);
            
            ResponseEvent response = snmp.set(pdu, target);

            if (response != null && response.getResponse() != null) {
                System.out.println("Resposta recebida: " + response.getResponse().getErrorStatusText());
            } else {
                System.err.println("Timeout ou nenhuma resposta do switch!");
            }

        } finally {
            if (snmp != null) {
                snmp.close();
            }
        }
    }
}
