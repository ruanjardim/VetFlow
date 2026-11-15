<?php

namespace Database\Seeders;

use App\Modules\Saas\Models\LegalDocument;
use Illuminate\Database\Seeder;
use Illuminate\Support\Facades\Schema;

class LegalDocumentSeeder extends Seeder
{
    public function run(): void
    {
        if (! Schema::hasTable('saas_legal_documents')) {
            return;
        }

        foreach ($this->documents() as $document) {
            $body = trim($document['body']);
            LegalDocument::query()->firstOrCreate(
                ['type' => $document['type'], 'version' => '2026.11-draft-1'],
                [
                    'title' => $document['title'],
                    'status' => 'draft',
                    'body' => $body,
                    'content_hash' => LegalDocument::hashBody($body),
                ]
            );
        }
    }

    /** @return array<int, array{type: string, title: string, body: string}> */
    private function documents(): array
    {
        return [
            [
                'type' => 'subscription-contract',
                'title' => 'Contrato de Assinatura SaaS VetFlow — Minuta',
                'body' => <<<'TEXT'
MINUTA PARA REVISÃO JURÍDICA — SEM VIGÊNCIA E SEM ACEITE OBRIGATÓRIO

CONTRATADA: [PREENCHER RAZÃO SOCIAL, CNPJ, ENDEREÇO E REPRESENTANTE LEGAL DO VETFLOW].
CONTRATANTE: o estabelecimento identificado na proposta comercial e no registro eletrônico de aceite, representado por pessoa com poderes para contratar.

1. Objeto. A CONTRATADA licencia, de forma temporária, onerosa, limitada e não exclusiva, o uso da plataforma VetFlow no plano e período descritos na proposta. O serviço compreende somente os módulos, limites de usuários e unidades expressamente indicados. Serviços de implantação avançada, mensagens pagas, hardware, comunicação direta com balanças e ativação fiscal dependem de proposta e homologação próprias.

2. Planos e preço. O preço-base, ciclo de cobrança, vencimento, tributos, quantidade de usuários e eventuais unidades adicionais devem constar da proposta e do resumo eletrônico aceito. Preços anuais e mensais não são cumulativos. Reajustes futuros deverão respeitar o índice, a periodicidade e a comunicação definidos na versão juridicamente revisada deste contrato.

3. Promoções e cupons. Um cupom válido concede apenas o percentual, prazo e plano registrados no resumo da contratação. Cupons não são cumulativos, não geram crédito, não reduzem valores anteriores e não se aplicam ao ciclo anual salvo disposição expressa. Encerrado o período promocional, a renovação volta ao preço normal informado antes do aceite. O registro eletrônico congela código, percentual, preço-base, desconto, preço final, início e fim.

4. Vigência, renovação e cancelamento. A vigência começa na data indicada no resumo comercial. Renovação, aviso prévio, cancelamento, eventual fidelidade e efeitos financeiros deverão ser definidos de forma clara na proposta e na versão final revisada. A suspensão por inadimplência não autoriza eliminação imediata de dados nem afasta obrigações legais.

5. Responsabilidades da CONTRATANTE. A CONTRATANTE deve manter dados cadastrais corretos, administrar usuários e permissões, proteger credenciais, usar equipamentos compatíveis, observar a legislação aplicável à própria operação e validar com contador ou profissional competente suas obrigações fiscais, trabalhistas, clínicas e regulatórias. O VetFlow não substitui decisão profissional veterinária, contábil, fiscal ou jurídica.

6. Disponibilidade e contingência. O modo offline do PDV possui limites documentados e exige preparação prévia do dispositivo autenticado. Não representa operação ilimitada, sincronização em tempo real entre vários dispositivos desconectados ou garantia contra falta de energia. Manutenções, suporte, backup, restauração e tratamento de incidentes seguem o anexo de Suporte, Backup e Disponibilidade.

7. Emissão fiscal e hardware. O comprovante padrão do PDV é não fiscal. NFC-e/NFS-e somente poderão ser ativadas em escopo suportado, com conta e credenciais do estabelecimento, validação do contador e homologação concluída. Etiquetas EAN de peso variável são o caminho padrão de balança; comunicação USB/serial direta depende do protocolo do modelo e de teste específico.

8. Dados e LGPD. Os papéis, instruções, medidas de segurança, subprocessadores, atendimento a titulares, incidentes, devolução e eliminação de dados constam do Anexo de Tratamento de Dados. A CONTRATANTE permanece responsável pelas decisões de finalidade e uso dos dados operacionais que inserir no sistema.

9. Propriedade intelectual. A CONTRATANTE conserva a titularidade dos dados da sua operação. Software, marca, código, estrutura, documentação e melhorias do VetFlow permanecem de titularidade da CONTRATADA ou de seus licenciantes. É proibido contornar controles, explorar vulnerabilidades, copiar ou sublicenciar o sistema.

10. Confidencialidade e segurança. As partes devem proteger informações confidenciais e comunicar incidentes relevantes pelos canais definidos. Nenhuma cláusula poderá excluir responsabilidade em desacordo com norma obrigatória. Limites indenizatórios, se adotados, exigem justificativa, destaque e revisão jurídica.

11. Saída e portabilidade. Após término ou cancelamento, a CONTRATANTE terá o período e os formatos definidos na proposta final para solicitar exportação disponível dos seus dados. Retenção obrigatória, anonimização, descarte e cópias de segurança seguirão a lei e a política publicada.

12. Aceite eletrônico. O aceite registra a identidade do usuário, empresa, versão, hash, data e hora, IP, navegador e resumo comercial. Uma cópia deve permanecer disponível. Alterações materiais exigem nova versão e, quando aplicável, novo aceite.

13. Lei aplicável e solução de conflitos. Aplicam-se as leis brasileiras. Foro, mediação e regras de atendimento devem ser definidos após revisão jurídica, sem afastar competência ou proteção obrigatória eventualmente aplicável.

Esta minuta não deve ser publicada como contrato vigente antes do preenchimento da CONTRATADA e da revisão por advogado brasileiro.
TEXT,
            ],
            [
                'type' => 'terms-of-use',
                'title' => 'Termos de Uso do VetFlow — Minuta',
                'body' => <<<'TEXT'
MINUTA PARA REVISÃO JURÍDICA — SEM VIGÊNCIA

1. Finalidade. Estes Termos disciplinam o acesso autorizado ao VetFlow por administradores, empregados e colaboradores de estabelecimentos contratantes.

2. Conta individual. Cada usuário deve utilizar conta própria, manter sua senha confidencial, ativar somente pessoas autorizadas e comunicar suspeitas de acesso indevido. Compartilhamento de credenciais, tentativa de contornar permissões e uso de identidade de terceiro são proibidos.

3. Poderes do administrador. O administrador do estabelecimento configura perfis, permissões, unidades e acessos. Ele deve revisar desligamentos, mudanças de função e necessidade de acesso. O VetFlow registra eventos técnicos e administrativos para segurança e auditoria.

4. Uso permitido. O sistema deve ser usado para atividades legítimas do estabelecimento. É proibido introduzir código malicioso, automatizar consultas abusivas, testar vulnerabilidades sem autorização, interferir na disponibilidade, extrair dados de outro cliente ou usar o serviço para violar direitos.

5. Conteúdo e decisões profissionais. O usuário responde pela exatidão e licitude do conteúdo inserido. Alertas, relatórios e sugestões do VetFlow são apoios operacionais; não substituem avaliação veterinária, contábil, tributária ou jurídica.

6. Vendas, fiscal e equipamentos. O comprovante padrão é não fiscal. A emissão fiscal depende de configuração, conta do estabelecimento, homologação e escopo territorial suportado. Compatibilidade com balança, impressora ou outro hardware somente existe quando documentada para o modelo e protocolo correspondentes.

7. Continuidade offline. A contingência do PDV exige dispositivo previamente preparado, catálogo local válido e contexto de caixa. Conflitos de estoque ou estado operacional podem exigir revisão depois da reconexão. Fluxos complexos indicados na interface permanecem online.

8. Atualizações. O VetFlow pode receber correções, melhorias e mudanças de segurança. Alterações materiais destes Termos serão versionadas, comunicadas e submetidas a novo aceite quando necessário.

9. Suspensão. Contas podem ser suspensas por solicitação do contratante, risco de segurança, violação destes Termos ou situação contratual prevista. A medida deve ser proporcional, registrada e não autoriza eliminação arbitrária dos dados.

10. Documentos relacionados. Integram o conjunto aplicável o Contrato de Assinatura, o Aviso de Privacidade, o Anexo de Tratamento de Dados e a Política de Suporte, Backup e Disponibilidade.

Esta minuta depende de revisão jurídica antes de publicação.
TEXT,
            ],
            [
                'type' => 'privacy-notice',
                'title' => 'Aviso de Privacidade VetFlow — Minuta',
                'body' => <<<'TEXT'
MINUTA PARA REVISÃO JURÍDICA — SEM VIGÊNCIA

1. Identificação. Controlador responsável por este aviso: [PREENCHER RAZÃO SOCIAL, CNPJ, ENDEREÇO E CANAL DE PRIVACIDADE DO VETFLOW].

2. Escopo. Este aviso trata dos dados usados pelo VetFlow para administrar contas comerciais, autenticar usuários, proteger a plataforma, prestar suporte, registrar aceite, operar cobrança contratada e manter comunicações administrativas. O tratamento dos dados operacionais inseridos pela clínica em nome de tutores, clientes e colaboradores é disciplinado também pelo Anexo de Tratamento de Dados.

3. Dados tratados. Podem ser tratados nome, e-mail, telefone, CPF/CNPJ, empresa, função, credenciais protegidas, permissões, registros de acesso, IP, navegador, eventos de segurança, solicitações de suporte, plano, promoção, pagamentos e evidências contratuais. Senhas são armazenadas por hash e não são incluídas em trilhas de auditoria.

4. Finalidades. As finalidades incluem executar o contrato, criar e proteger contas, prestar suporte, cumprir obrigações legais, prevenir fraude, demonstrar aceite, manter continuidade, enviar comunicações essenciais e aprimorar segurança e desempenho com dados proporcionais.

5. Bases legais. As bases aplicáveis serão definidas por finalidade na revisão jurídica, podendo incluir execução de contrato, cumprimento de obrigação legal ou regulatória, exercício regular de direitos e legítimo interesse sujeito a avaliação e salvaguardas. Consentimento não será usado de forma genérica quando outra base for adequada.

6. Compartilhamento. Dados poderão ser compartilhados, na medida necessária, com hospedagem, e-mail, monitoramento, suporte, pagamento contratado e autoridades competentes. A lista de subprocessadores e transferências internacionais deverá ser publicada antes da vigência.

7. Retenção. Dados serão mantidos pelo tempo necessário à finalidade, à execução contratual, à segurança, à defesa de direitos e às obrigações legais. Prazos operacionais e rotina de eliminação deverão ser aprovados e documentados.

8. Segurança. São usados controles de acesso, segregação por estabelecimento, criptografia nos fluxos definidos, logs, backups e processos de resposta a incidentes. Nenhum sistema é isento de risco; incidentes relevantes seguirão o plano de resposta e a legislação.

9. Direitos. Titulares poderão solicitar confirmação, acesso, correção e demais direitos aplicáveis pelo canal de privacidade. Antes de responder, será necessário validar identidade, competência e o papel de controlador ou operador no tratamento solicitado.

10. Atualizações e contato. Mudanças materiais serão versionadas. Contato de privacidade: [PREENCHER E-MAIL E RESPONSÁVEL OU ENCARREGADO, QUANDO APLICÁVEL].

Esta minuta depende do inventário final de dados, subprocessadores e revisão jurídica.
TEXT,
            ],
            [
                'type' => 'data-processing',
                'title' => 'Anexo de Tratamento de Dados — Minuta',
                'body' => <<<'TEXT'
MINUTA PARA REVISÃO JURÍDICA — SEM VIGÊNCIA

1. Partes e papéis. Para dados operacionais inseridos pelo estabelecimento, a CONTRATANTE normalmente define finalidades e meios essenciais como controladora, e a CONTRATADA trata os dados conforme instruções documentadas como operadora. Para conta comercial, segurança, faturamento e defesa dos próprios direitos, a CONTRATADA poderá atuar como controladora independente. Cada tratamento deve ser classificado pela realidade, não apenas pelo nome contratual.

2. Objeto e duração. O tratamento abrange hospedagem, organização, consulta, suporte, cópia de segurança, exportação e eliminação necessárias ao VetFlow durante a vigência e pelos prazos posteriores aprovados.

3. Categorias. Podem existir dados de representantes, funcionários, profissionais, tutores e outros contatos cadastrados, incluindo identificação, contato, relacionamento comercial, permissões e registros operacionais. A CONTRATANTE deve evitar inserir dados excessivos ou sem finalidade legítima.

4. Instruções. A CONTRATADA tratará dados operacionais segundo o contrato, configurações do sistema e instruções lícitas da CONTRATANTE. Instruções incompatíveis com a lei ou segurança deverão ser documentadas e poderão ser recusadas.

5. Confidencialidade e acesso. Pessoas autorizadas ficam sujeitas a dever de confidencialidade e acesso proporcional à função. A CONTRATANTE administra seus usuários e deve remover acessos desnecessários.

6. Segurança. A CONTRATADA manterá medidas técnicas e administrativas proporcionais, incluindo isolamento lógico por clínica, autenticação, autorização, registros de auditoria, proteção de credenciais, gestão de vulnerabilidades, backup e resposta a incidentes. Os anexos finais devem descrever controles sem expor segredos de segurança.

7. Suboperadores. Hospedagem e outros fornecedores essenciais deverão constar de lista pública ou anexo. Inclusões materiais serão comunicadas conforme prazo a definir. A CONTRATADA exigirá obrigações compatíveis e permanecerá responsável pelos seus deveres legais.

8. Titulares e autoridades. Solicitações recebidas pela CONTRATADA sobre dados controlados pela CONTRATANTE serão encaminhadas ou atendidas conforme instrução, competência e prazo aplicável. As partes cooperarão com a ANPD e outras autoridades.

9. Incidentes. A CONTRATADA comunicará à CONTRATANTE incidente confirmado que possa afetar seus dados, com informações disponíveis e atualizações razoáveis. Prazo operacional, responsáveis e canal devem ser definidos após validação do plano de resposta; o anexo não promete prazo impossível antes da apuração mínima.

10. Retorno, exportação e eliminação. Ao encerrar o serviço, os dados serão disponibilizados nos formatos suportados durante o período contratado. Depois, serão eliminados ou anonimizados, ressalvadas retenções legais, defesa de direitos e ciclos técnicos de backup documentados.

11. Auditoria e evidência. A CONTRATADA fornecerá evidências razoáveis de conformidade sem revelar dados de outros clientes, segredos comerciais ou informações que reduzam a segurança. Auditorias presenciais ou extraordinárias dependerão de escopo, confidencialidade e proporcionalidade.

12. Transferência internacional. Localização de hospedagem, fornecedores e salvaguardas de transferência deverão ser preenchidas e revisadas antes da publicação.

Esta minuta exige inventário de subprocessadores, prazos e revisão jurídica.
TEXT,
            ],
            [
                'type' => 'support-and-backup',
                'title' => 'Política de Suporte, Backup e Disponibilidade — Minuta',
                'body' => <<<'TEXT'
MINUTA OPERACIONAL PARA VALIDAÇÃO E REVISÃO JURÍDICA — SEM SLA VIGENTE

1. Canais. O canal comercial atual é comercial@vetflowsys.com.br. Antes da vigência deverão ser publicados o canal de suporte, o responsável por incidentes, o horário de atendimento e a forma de escalonamento. Mensagens enviadas a canais pessoais não substituem protocolo.

2. Prioridades propostas. P1: indisponibilidade geral, risco confirmado de segurança ou impossibilidade de vender sem contingência. P2: função essencial degradada com alternativa parcial. P3: erro localizado sem bloqueio da operação. P4: dúvida, melhoria ou ajuste. Tempos de resposta e solução somente serão prometidos após validação da capacidade de suporte.

3. Disponibilidade. A aplicação depende de internet, hospedagem, banco de dados, energia e serviços de terceiros. O VetFlow manterá monitoramento e processo de incidente compatíveis com o plano contratado, mas não promete disponibilidade absoluta. Manutenções programadas serão comunicadas quando materialmente relevantes.

4. PDV offline. Um dispositivo previamente autenticado e preparado pode reabrir o PDV e enfileirar vendas regulares suportadas. A contingência não cobre início frio em aparelho não preparado, vários aparelhos desconectados compartilhando estoque em tempo real, pagamento dependente de provedor online, venda fiada, crédito de cliente ou outros fluxos indicados como online. Conflitos permanecem visíveis para revisão.

5. Backups. O ambiente de produção deve possuir backup do banco e armazenamento persistente conforme capacidade contratada do provedor. A existência de um arquivo não prova restauração. O VetFlow mantém procedimento de teste isolado e somente declarará objetivo de recuperação depois de registrar evidência real de backup e restauração.

6. Restauração. Restaurações nunca serão testadas sobre o banco ativo. O procedimento utiliza ambiente isolado, valida migrações e totais de controle, preserva evidência segura e remove credenciais temporárias. RPO, RTO e retenção serão preenchidos após o primeiro exercício aprovado.

7. Incidentes. A resposta inclui triagem, preservação de evidências, contenção, recuperação, comunicação às partes responsáveis e análise posterior. Incidentes de privacidade seguem a LGPD e a decisão do controlador competente.

8. Responsabilidades do cliente. O cliente deve manter contatos atualizados, controlar usuários, usar equipamentos adequados, testar sua conexão e nobreak quando necessários, observar alertas e comunicar falhas com horário, usuário, tela e operação afetada, sem enviar senhas ou credenciais fiscais.

9. Exclusões. Suporte não inclui consultoria veterinária, contábil, fiscal ou jurídica; reparo de hardware; rede local; configuração genérica de balança sem protocolo homologado; ou serviço de terceiros contratado diretamente pelo cliente, salvo proposta específica.

10. Transparência. Interrupções relevantes, mudanças desta política e parâmetros aprovados serão registrados e comunicados. Até que horários, tempos, RPO, RTO e retenção sejam validados, esta minuta não pode ser anunciada como SLA contratual.
TEXT,
            ],
        ];
    }
}
