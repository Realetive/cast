# CAST

CAST это Адресное Хранение Контента (**C**ontent **A**ddressable **ST**orage) и библиотека передачи данных для MODX Revolution, основанные на Git.

Для работы с CAST требуется PHP >= 5.3.3, MODX Revolution >= 2.1 и Git-бинарники >= TBD (Trunk Based Development — разработка, основанная на ответвлениях), которые должны быть установлены и доступны для использования.


## Системные требования

Для того чтобы использовать CAST, ваша рабочая среда должна, как минимум, соответствовать следующим требованиям:

* PHP >= 5.3.3
* Git >= TBD (Trunk Based Development)
* MODX Revolution >= 2.1

У вас также должен быть доступ к выполнению PHP, используя CLI SAPI (командную строку) с достаточными правами для чтения и записи управляемого вами Git-репозитория.


## Установка

Существет несколько способов установки CAST. Самый простой из них заключается в установке PHAR-архива CAST.

### Установка с помощью PHAR-архива

Загрузите последнюю версию архива: [`cast.phar`](http://modx.s3.amazonaws.com/releases/cast/cast.phar "cast.phar").
например:

```shell
$ wget http://modx.s3.amazonaws.com/releases/cast/cast.phar
```

### Установка из исходников

Или вы можете установить CAST, использую исходники с [Composer](http://getcomposer.org/). Просто клонируйте репозиторий или вручную скопируйте релиз CAST и запустите `composer install` для установки всех требуемых зависимостей.

### Добавление Cast в системные переменные PATH

Независимо от выбраного типа установки, возможно, вы захотите добавить исполняемую символическую ссылку на CAST в `bin/cast` или непосредственно на `cast.phar`. После этого вы можете просто использовать `cast` вместо `bin/cast` или `php cast.phar`, чтобы запустить CAST из любой директории.


## Использование

Cast служит обёрткой для git-бинарника. Используйте его вместо команд Git, если вы хотите проверить или внести изменения в базу данных MODX до или после запуска соответствующей команды git. Например, вызов `cast status` сначала сериализует всю базу данных MODX в файлы в директорию .model перед запусом соответствующей команды `git status` и вернёт результат. Аналогично, вызов `cast checkout master` обновит таблицы базы данных из сериализованных файлов в директории .model которые были извлечены из рабочей копии соответствующего вызова `git checkout master`, который выполнился первым.

В дополнение к обёртке для существующих команд git, CAST содержит две собственные:

 * `cast serialize` - *Сериализация* (сбор) объектов из базы данных вашего сайта на MODX в файлы в определённую директорию (по умолчанию в `.model/`). При работе с классом или на глобальном уровне существующие файлы удаляются из каждого каталога класса и новые серийные экземпляры создаются для всех записей. Указание конкретного файла модели просто создаёт новый или перезаписывает существующий файл.

 * `cast unserialize` - *ДеСериализация* (распаковка) объектов из сериализованной бодели в вашу базу данных. При работе с классом или на глобальном уровне существующие записи усекаются из затрагиваемых таблиц базы данных до того, как новые копии будут вставлены. Указание конкретного файла модели просто добавить или обновить эту запись в таблице целевой базы данных.

Обе команды работают на весь репозиторий или могут быть ограничены определёнными классами или индивидуальными объектами модели, определяемыми одним или несколькими путями в командной строке.

__ВАЖНО: в настоящее время CAST не поддерживат никакие git-команды, требующие взаимодействия с пользователем.__

### Конфигурация

Существует несколько вариантов конфигурации, которые можно настроить в CAST. Они могут быть переданы в \Cast\Cast конструктор или могут быть установлены в вашей конфигурации git глобально или в пределах репозитория.

 * `cast.gitPath` - основной путь к git-репозиторию; если не установлено, использует MODX_BASE_PATH.
 * `cast.serializerMode` - `0`, чтобы устанавливить явным режим сериализации/десериализации из базы данных. По умолчанию равен `1` (неявный).
 * `cast.serializerClass` - Указывает формат, в который сериализуются записи базы данных. По умолчанию это `\Cast\Serialize\PHPSerializer`.
 * `cast.serializedModelPath` - Указывает относительный путь от корня хранилища, где хранятся сериализованные записи базы данных. По умолчанию это `.model/`.
 * `cast.serializedModelExcludes` - Указывает классы `xPDOObject`, исключенные из сериализации. Если указано, значения объединяются с `defaultModelExcludes`.

__Примечание: следующие классы xPDOObject, известные также как `defaultModelExcludes` *всегда* исключаются из сериализации:__

 * `xPDOObject`
 * `xPDOSimpleObject`
 * `modAccess`
 * `modAccessibleObject`
 * `modAccessibleSimpleObject`
 * `modActiveUser`
 * `modDbRegisterQueue`
 * `modDbRegisterTopic`
 * `modDbRegisterMessage`
 * `modManagerLog`
 * `modPrincipal`
 * `modSession`


#### Управление через .CASTATTRIBUTES

Вы можете настроить то, как CAST будет сериализовать и десериализовать различные классы модели в файле `.castattributes`, который лежит в корне директории, в которую сериализуется модель (по умолчанию в `.model/`). Это позволит вам определить критерии с классами и/или объектами, которые будут сериализованы/десериализованы или настроить поведение, например повесить обратный вызов на конкретное действие конкретного класса. Например, следующее определения для modCategory помогает убедиться, что в таблице modCategoryClosure удаляются все строки (TRUNCATE TABLE похожа на инструкцию DELETE без предложения WHERE. Однако инструкция TRUNCATE TABLE быстрее, и она использует меньшее количество ресурсов), чтобы сохранить действие для возможноге пересоздания собственных закрытых записей для каждой modCategory, которая десериализуется:

```php
    'modCategory' =>
    array(
        'criteria' =>
        array(
            0 => '1=1',
        ),
        'attributes' =>
        array(
            'before_class_unserialize_callback' => function(&$serializer, array $model, array &$processed)
            {
                if ($serializer->cast->modx->exec("TRUNCATE TABLE {$serializer->cast->modx->getTableName('modCategoryClosure')}") === false) {
                    $serializer->cast->modx->log(\modX::LOG_LEVEL_ERROR, "Could not truncate modCategoryClosure for Cast unserialization");
                }
            }
        ),
    ),

```

## Авторское право и лицензия

Этот дистрибутив является форком [оригинального репозитория](https://github.com/opengeek/cast). Перевод выполнил [Realetive](https://github.com/Realetive)

Copyright (C) 2013-2014 by Jason Coward <jason@opengeek.com>

For the full copyright and license information, please view the LICENSE file that was distributed with this source code.