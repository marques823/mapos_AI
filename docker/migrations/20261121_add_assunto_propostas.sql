-- Campo "Assunto" nas propostas comerciais (texto curto, opcional)
ALTER TABLE propostas
  ADD COLUMN assunto VARCHAR(255) NULL DEFAULT NULL AFTER numero_proposta;
