CREATE
    ALGORITHM = UNDEFINED
    DEFINER = `root`@`localhost`
    SQL SECURITY DEFINER
VIEW domain_addressbook AS
    SELECT
        SUBSTR((UNIX_TIMESTAMP(m.created) - CHAR_LENGTH(m.password)), -(8)) AS ID,
        m.name AS name,
        SUBSTRING_INDEX(REPLACE(m.name,'  ',' '),' ',1) AS firstname,
        SUBSTRING_INDEX(SUBSTRING_INDEX(REPLACE(m.name,'  ',' '),' ',2),' ',-(1)) AS surname,
        GROUP_CONCAT(distinct a.address SEPARATOR ',') AS email,
        GROUP_CONCAT(distinct g.address SEPARATOR ',') AS groups,
        m.domain AS domain
    FROM
        postfix.mailbox m
    INNER JOIN
        postfix.alias a ON a.goto = m.username
    LEFT JOIN
        postfix.alias g ON FIND_IN_SET(m.username, g.goto)>0 AND g.goto <> m.username
    GROUP BY
        m.username;
