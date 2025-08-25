# Red Social Inmobiliaria

Una red social enfocada en bienes raíces con diferentes tipos de usuarios y control de acceso por roles.

## Características

- **Frontend**: HTML + CSS (Bootstrap) + JavaScript (Fetch API)
- **Backend**: PHP 8 + MySQL con arquitectura sencilla y router propio
- **APIs REST** para autenticación, gestión de usuarios, publicaciones, imágenes y mensajes
- **Control de acceso por roles** (superuser, administrador, vendedor, comprador)
- **Carga de imágenes** al servidor
- **Sistema de aprobación** de usuarios

## Roles y Permisos

### Superusuario (superuser)
- Aprueba/rechaza registros nuevos
- Eleva roles de usuarios
- Crea administradores
- Acceso completo al sistema

### Administrador (admin)
- Puede editar/eliminar publicaciones de cualquier usuario
- Ver y banear usuarios
- Gestión de contenido

### Vendedor (seller)
- Crea/edita/elimina sus publicaciones
- Sube fotos, ubicación y características de propiedades
- Recibe mensajes de compradores

### Comprador (buyer)
- Navega publicaciones
- Envía mensajes/contacto al vendedor
- Busca propiedades con filtros

## Estados de Usuario

- **pending**: Usuario registrado pero pendiente de aprobación
- **approved**: Usuario aprobado y activo
- **banned**: Usuario baneado (sin acceso)

## Estructura del Proyecto

```
realestate-social/
├── public/                 # Directorio público (DocumentRoot)
│   ├── index.php          # Router principal
│   ├── .htaccess          # Configuración Apache
│   ├── css/
│   │   └── styles.css     # Estilos personalizados
│   └── js/
│       └── app.js         # JavaScript principal
├── api/                   # Endpoints de la API
│   ├── auth.php           # Autenticación
│   ├── users.php          # Gestión de usuarios
│   ├── posts.php          # Gestión de publicaciones
│   ├── uploads.php        # Carga de imágenes
│   └── messages.php       # Mensajería
├── views/                 # Páginas HTML
│   ├── login.html         # Página de login
│   ├── register.html      # Página de registro
│   ├── feed.html          # Feed principal
│   ├── post_form.html     # Formulario de publicación
│   └── admin_dashboard.html # Panel de administración
├── core/                  # Núcleo del sistema
│   ├── db.php             # Conexión a base de datos
│   ├── response.php       # Helpers de respuesta
│   ├── session.php        # Gestión de sesiones
│   └── guard.php          # Control de acceso
├── config/                # Configuración
│   └── config.php         # Configuración principal
├── sql/                   # Base de datos
│   └── schema.sql         # Esquema de la base de datos
└── uploads/               # Directorio de imágenes subidas
```

## Instalación

### Requisitos

- PHP 8.0 o superior
- MySQL 5.7 o superior
- Apache con mod_rewrite habilitado
- Extensiones PHP: PDO, PDO_MySQL, GD, fileinfo

### Pasos de Instalación

1. **Clonar o descargar el proyecto**
   ```bash
   git clone <repository-url> realestate-social
   cd realestate-social
   ```

2. **Configurar la base de datos**
   ```bash
   # Crear la base de datos
   mysql -u root -p
   CREATE DATABASE realestate_social;
   exit
   
   # Importar el esquema
   mysql -u root -p realestate_social < sql/schema.sql
   ```

3. **Configurar la aplicación**
   ```bash
   # Copiar y editar la configuración
   cp config/config.php config/config.local.php
   ```
   
   Editar `config/config.php` con tus datos de base de datos:
   ```php
   'db_host' => 'localhost',
   'db_name' => 'realestate_social',
   'db_user' => 'tu_usuario',
   'db_pass' => 'tu_contraseña',
   ```

4. **Configurar permisos**
   ```bash
   # Crear directorio de uploads y dar permisos
   mkdir uploads
   chmod 755 uploads
   
   # Permisos para Apache
   chown -R www-data:www-data .
   chmod -R 755 .
   ```

5. **Configurar Apache**
   
   Configurar el DocumentRoot hacia la carpeta `public/`:
   ```apache
   <VirtualHost *:80>
       ServerName realestate-social.local
       DocumentRoot /path/to/realestate-social/public
       
       <Directory /path/to/realestate-social/public>
           AllowOverride All
           Require all granted
       </Directory>
   </VirtualHost>
   ```

6. **Acceder a la aplicación**
   
   Abrir en el navegador: `http://realestate-social.local`

## Usuario por Defecto

El sistema incluye un superusuario por defecto:

- **Usuario**: admin
- **Email**: admin@realestate.com
- **Contraseña**: admin123

**¡IMPORTANTE!** Cambiar esta contraseña inmediatamente en producción.

## API Endpoints

### Autenticación
- `POST /api/auth.php?action=login` - Iniciar sesión
- `POST /api/auth.php?action=register` - Registrarse
- `POST /api/auth.php?action=logout` - Cerrar sesión
- `GET /api/auth.php?action=me` - Obtener usuario actual

### Usuarios
- `GET /api/users.php?action=pending` - Usuarios pendientes (superuser)
- `GET /api/users.php?action=all` - Todos los usuarios (admin+)
- `POST /api/users.php?action=approve` - Aprobar usuario (superuser)
- `POST /api/users.php?action=reject` - Rechazar usuario (superuser)
- `POST /api/users.php?action=ban` - Banear usuario (admin+)
- `POST /api/users.php?action=unban` - Desbanear usuario (admin+)
- `POST /api/users.php?action=promote` - Cambiar rol (superuser)

### Publicaciones
- `GET /api/posts.php` - Listar publicaciones
- `GET /api/posts.php?action=detail&id=X` - Detalle de publicación
- `GET /api/posts.php?action=my` - Mis publicaciones (seller)
- `POST /api/posts.php` - Crear publicación (seller)
- `PUT /api/posts.php?id=X` - Actualizar publicación
- `DELETE /api/posts.php?id=X` - Eliminar publicación

### Imágenes
- `POST /api/uploads.php` - Subir imagen
- `DELETE /api/uploads.php?id=X` - Eliminar imagen

### Mensajes
- `GET /api/messages.php` - Listar conversaciones
- `GET /api/messages.php?action=conversation&user_id=X` - Ver conversación
- `GET /api/messages.php?action=unread` - Contar no leídos
- `POST /api/messages.php` - Enviar mensaje
- `PUT /api/messages.php?id=X` - Marcar como leído
- `DELETE /api/messages.php?id=X` - Eliminar mensaje

## Desarrollo

### Estructura de la Base de Datos

- **users**: Usuarios del sistema
- **posts**: Publicaciones de propiedades
- **post_images**: Imágenes de las publicaciones
- **messages**: Mensajes entre usuarios
- **user_sessions**: Sesiones de usuario (opcional)

### Seguridad

- Validación de entrada en todas las APIs
- Protección contra SQL injection usando PDO prepared statements
- Control de acceso basado en roles
- Validación de tipos de archivo para uploads
- Headers de seguridad configurados
- Sesiones seguras

### Personalización

Para personalizar la aplicación:

1. **Estilos**: Editar `public/css/styles.css`
2. **JavaScript**: Editar `public/js/app.js`
3. **Configuración**: Editar `config/config.php`
4. **Base de datos**: Modificar `sql/schema.sql`

## Troubleshooting

### Problemas Comunes

1. **Error de conexión a la base de datos**
   - Verificar credenciales en `config/config.php`
   - Asegurar que MySQL esté ejecutándose
   - Verificar que la base de datos existe

2. **Error 500 en Apache**
   - Verificar logs de Apache: `tail -f /var/log/apache2/error.log`
   - Verificar permisos de archivos
   - Verificar que mod_rewrite esté habilitado

3. **Imágenes no se suben**
   - Verificar permisos del directorio `uploads/`
   - Verificar configuración PHP: `upload_max_filesize`, `post_max_size`
   - Verificar espacio en disco

4. **Sesiones no funcionan**
   - Verificar configuración de sesiones en PHP
   - Verificar permisos del directorio de sesiones

### Logs

Los errores se registran en:
- Logs de Apache: `/var/log/apache2/error.log`
- Logs de PHP: Configurar en `php.ini`

## Contribución

1. Fork el proyecto
2. Crear una rama para tu feature (`git checkout -b feature/AmazingFeature`)
3. Commit tus cambios (`git commit -m 'Add some AmazingFeature'`)
4. Push a la rama (`git push origin feature/AmazingFeature`)
5. Abrir un Pull Request

## Licencia

Este proyecto está bajo la Licencia MIT. Ver el archivo `LICENSE` para más detalles.

## Soporte

Para soporte técnico o preguntas:
- Crear un issue en el repositorio
- Contactar al equipo de desarrollo

---

**Nota**: Este es un proyecto de demostración. Para uso en producción, implementar medidas adicionales de seguridad y optimización.
