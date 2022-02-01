# ϟ StORM
ORM knihovna pro práci s databázi, postavená PDO a lehce integrovatelná s Nette frameworkem

![Actions](https://github.com/liquiddesign/storm/actions/workflows/php.yml/badge.svg)
![Release](https://img.shields.io/github/v/release/liquiddesign/storm)


<rule ref="Squiz.WhiteSpace.OperatorSpacing"/>


    <rule ref="Generic.PHP.ForbiddenFunctions">
        <properties>
            <property name="forbiddenFunctions" type="array">
                <element key="chop" value="rtrim"/>
                <element key="close" value="closedir"/>
            </property>
        </properties>
    </rule>