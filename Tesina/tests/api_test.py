#!/usr/bin/env python3
"""
Pruebas de integración de la API de MaxRep (CU-01 a CU-07).

Requiere el sitio corriendo y Python 3 con `requests`.
No borra datos: crea usuarios nuevos con correos aleatorios. Conviene usar una base de prueba.

    MAXREP_URL=http://localhost/Tesina python tests/api_test.py
    # Opcional, para probar la expiración de sesión (CU-02, 2.3): un segundo servidor
    # con MAXREP_SESSION_IDLE=2 apuntando a la misma base.
    MAXREP_URL_SHORT=http://127.0.0.1:8081/Tesina python tests/api_test.py
"""
import io
import os
import re
import sys
import time
import uuid
import datetime as dt

import requests

BASE = os.environ.get("MAXREP_URL", "http://127.0.0.1:8080/Tesina").rstrip("/")
SHORT = os.environ.get("MAXREP_URL_SHORT", "").rstrip("/")
PASS = "Prueba123"
results = []


def check(name, cond, detail=""):
    results.append((name, bool(cond), detail))
    print(("  OK  " if cond else "  FALLA ") + name + ("" if cond else f"  → {detail}"))


class Client:
    def __init__(self, base=BASE):
        self.base = base
        self.s = requests.Session()
        self.csrf = None
        self.refresh_csrf()

    def refresh_csrf(self, page="Cuenta/login.php"):
        html = self.s.get(f"{self.base}/{page}").text
        m = re.search(r'name="csrf-token" content="([a-f0-9]+)"', html)
        self.csrf = m.group(1) if m else None

    def get(self, path, **params):
        r = self.s.get(f"{self.base}/{path}", params=params)
        return r.status_code, safe_json(r)

    def post(self, path, body=None, files=None, data=None, csrf=True):
        h = {"X-CSRF-Token": self.csrf} if csrf else {}
        if files is not None or data is not None:
            r = self.s.post(f"{self.base}/{path}", data=data or {}, files=files, headers=h)
        else:
            r = self.s.post(f"{self.base}/{path}", json=body or {}, headers=h)
        return r.status_code, safe_json(r)


def safe_json(r):
    try:
        return r.json()
    except ValueError:
        return {"_raw": r.text[:300]}


def email():
    return f"t{uuid.uuid4().hex[:10]}@test.local"


def register(c, role="usuario", name="Tester"):
    e = email()
    st, j = c.post("Api/auth.php?action=register", {"role": role, "name": name, "email": e, "password": PASS, "password2": PASS})
    return st, j, e


def png_bytes(extra=0):
    # PNG 1x1 válido + relleno opcional (sigue siendo PNG para finfo/getimagesize)
    base = bytes.fromhex(
        "89504e470d0a1a0a0000000d4948445200000001000000010806000000"
        "1f15c4890000000d49444154789c6360000002000154a24f5f0000000049454e44ae426082")
    return base + b"\0" * extra


def main():
    today = dt.date.today()
    print(f"MaxRep API tests contra {BASE}\n")

    # ------------------------------------------------------------------ seguridad básica
    print("Transversal: autenticación y CSRF")
    anon = Client()
    st, j = anon.get("Api/dashboard.php")
    check("API sin sesión → 401 unauthenticated", st == 401 and j.get("code") == "unauthenticated", (st, j))
    r = anon.s.get(f"{BASE}/index.php", allow_redirects=False)
    check("Página sin sesión redirige al login", r.status_code == 302 and "Cuenta/login.php" in r.headers.get("Location", ""), r.status_code)
    st, j = anon.post("Api/auth.php?action=register", {"role": "usuario"}, csrf=False)
    check("POST sin token CSRF → 419", st == 419 and j.get("code") == "csrf", (st, j))

    # ------------------------------------------------------------------ CU-01
    print("\nCU-01 Registrarse")
    a = Client()
    st, j, a_email = register(a, "usuario", "Alumna <script>alert(1)</script>")
    check("Registro de Usuario → 201 y redirige a Inicio", st == 201 and j.get("redirect", "").endswith("/index.php"), (st, j))
    st, j = a.get("Api/auth.php", action="session")
    check("Queda con la sesión iniciada", j.get("authenticated") is True, j)
    html = a.s.get(f"{BASE}/index.php").text
    check("Nombre con HTML se muestra escapado (XSS)", "&lt;script&gt;" in html and "<script>alert(1)" not in html)

    x = Client()
    st, j = x.post("Api/auth.php?action=register", {"role": "usuario", "name": "Dup", "email": a_email.upper(), "password": PASS, "password2": PASS})
    check("1.1 Correo existente (sin distinguir mayúsculas) → 409 'Este correo ya está en uso'",
          st == 409 and j.get("error") == "Este correo ya está en uso" and j.get("field") == "email", (st, j))
    st, j = x.post("Api/auth.php?action=register", {"role": "usuario", "name": "W", "email": email(), "password": "abcdefgh", "password2": "abcdefgh"})
    check("1.2 Contraseña débil → 422 con requisitos faltantes", st == 422 and set(j.get("missing", [])) == {"upper", "number"}, (st, j))
    st, j = x.post("Api/auth.php?action=register", {"role": "usuario", "name": "", "email": "", "password": PASS, "password2": PASS})
    check("1.3 Campos obligatorios vacíos → 422 con la lista de campos", st == 422 and j.get("fields") == ["name", "email"], (st, j))
    st, j = x.post("Api/auth.php?action=register", {"role": "admin", "name": "N", "email": email(), "password": PASS, "password2": PASS})
    check("Rol inválido rechazado", st == 422 and j.get("code") == "role", (st, j))
    st, j = x.post("Api/auth.php?action=register", {"role": "usuario", "name": "N", "email": email(), "password": PASS, "password2": PASS + "x"})
    check("Contraseñas distintas rechazadas", st == 422 and j.get("field") == "password2", (st, j))

    p = Client()
    st, j, p_email = register(p, "profesor", "Profe Test")
    st2, prof = p.get("Api/perfil.php")
    code = prof["profile"]["codigo"]
    check("Registro de Profesor genera código de 8 caracteres", st == 201 and re.fullmatch(r"[A-Z2-9]{8}", code or ""), prof)

    # login
    l = Client()
    st, j = l.post("Api/auth.php?action=login", {"user": a_email, "password": "Mala1234"})
    check("Login con contraseña incorrecta → 401", st == 401, (st, j))
    st, j = l.post("Api/auth.php?action=login", {"user": a_email, "password": PASS, "next": "/Tesina/Ejercicios/Ejercicios.php"})
    check("Login correcto respeta 'next' interno", st == 200 and j.get("redirect", "").endswith("Ejercicios/Ejercicios.php"), (st, j))
    l2 = Client()
    st, j = l2.post("Api/auth.php?action=login", {"user": a_email, "password": PASS, "next": "https://evil.example/"})
    check("Login ignora 'next' externo (sin redirección abierta)", st == 200 and "evil" not in j.get("redirect", ""), j)
    t = Client()
    for _ in range(5):
        t.post("Api/auth.php?action=login", {"user": a_email, "password": "Mala1234"})
    st, j = t.post("Api/auth.php?action=login", {"user": a_email, "password": PASS})
    check("Tras 5 intentos fallidos se frena el login (429)", st == 429, (st, j))

    # ------------------------------------------------------------------ CU-03 (usuario nuevo)
    print("\nCU-03 Inicio")
    st, j = a.get("Api/dashboard.php")
    check("3.2 Usuario nuevo → isNew (estados vacíos)", st == 200 and j.get("isNew") is True, j)

    # ------------------------------------------------------------------ CU-02
    print("\nCU-02 Perfil")
    st, j = a.post("Api/perfil.php?action=update", data={"name": "Alumna", "peso": "abc", "altura": "170", "objetivos": ""})
    check("2.1 Letras en el peso → 422 con error en 'peso'", st == 422 and "peso" in j.get("fields", {}), (st, j))
    st, j = a.post("Api/perfil.php?action=update", data={"name": "Alumna", "peso": "68,5", "altura": "170", "objetivos": "Ganar fuerza"})
    check("Guardar peso, altura y objetivos", st == 200 and j["profile"]["peso"] == 68.5 and j["profile"]["altura"] == 170, (st, j))
    st, j = a.post("Api/perfil.php?action=update", data={"name": "Alumna", "peso": "68.5", "altura": "170"},
                   files={"photo": ("foto.png", png_bytes(), "image/png")})
    check("Subir foto PNG válida", st == 200 and "/uploads/perfiles/" in j["profile"]["photo"], (st, j))
    photo_url = j.get("profile", {}).get("photo", "")
    if photo_url:
        r = a.s.get("http://" + BASE.split("/")[2] + photo_url)
        check("La foto subida se sirve", r.status_code == 200 and r.content[:4] == b"\x89PNG", r.status_code)
    st, j = a.post("Api/perfil.php?action=update", data={"name": "Alumna"},
                   files={"photo": ("doc.txt", b"hola, no soy una imagen", "text/plain")})
    check("2.2 Formato no admitido → 'Formato inválido o excede 5MB'", st == 422 and j.get("fields", {}).get("photo") == "Formato inválido o excede 5MB", (st, j))
    st, j = a.post("Api/perfil.php?action=update", data={"name": "Alumna"},
                   files={"photo": ("grande.png", png_bytes(5 * 1024 * 1024 + 10), "image/png")})
    check("2.2 Foto de más de 5MB rechazada", st in (413, 422) and "excede 5MB" in str(j), (st, j))
    st, j = a.post("Api/perfil.php?action=update", data={"name": "Alumna"},
                   files={"photo": ("evil.php.png", b"<?php echo 1; ?>", "image/png")})
    check("Archivo PHP disfrazado de PNG rechazado", st == 422, (st, j))

    if SHORT:
        s = Client(SHORT)
        st, j, s_email = register(s, "usuario", "Sesion Corta")
        time.sleep(3.2)
        st, j = s.post("Api/perfil.php?action=update", data={"name": "Cambio"})
        check("2.3 Sesión vencida al guardar → 401 session_expired", st == 401 and j.get("code") == "session_expired", (st, j))
        st, j = s.post("Api/auth.php?action=reauth", {"password": "otra"})
        check("2.3 Reautenticación con clave errónea → 401", st == 401, (st, j))
        st, j = s.post("Api/auth.php?action=reauth", {"password": PASS})
        check("2.3 Reautenticación correcta", st == 200, (st, j))
        st, j = s.post("Api/perfil.php?action=update", data={"name": "Cambio"})
        check("2.3 Reintento del guardado tras reautenticar", st == 200 and j["profile"]["name"] == "Cambio", (st, j))
    else:
        print("  (omitido) 2.3 expiración: definí MAXREP_URL_SHORT")

    # ------------------------------------------------------------------ CU-06
    print("\nCU-06 Ejercicios")
    st, j = a.get("Api/ejercicios.php", action="list")
    check("Biblioteca completa", st == 200 and len(j["items"]) >= 100, len(j.get("items", [])))
    st, j = a.get("Api/ejercicios.php", action="list", q="sentadilla")
    check("Búsqueda 'sentadilla' filtra resultados", st == 200 and j["items"] and all("entadilla" in i["name"] for i in j["items"]), j.get("items", [])[:2])
    st, j = a.get("Api/ejercicios.php", action="list", q="xyzqwerty")
    check("6.1 Búsqueda sin resultados devuelve lista vacía", st == 200 and j["items"] == [], j)
    st, j = a.get("Api/ejercicios.php", action="list", q="' OR 1=1 -- ")
    check("Inyección SQL en la búsqueda no devuelve todo", st == 200 and j["items"] == [], len(j.get("items", [])))
    st, j = a.get("Api/ejercicios.php", action="list", mu="5", type="Libre")
    check("Filtro por músculo y tipo", st == 200 and j["items"] and all(i["mu"] == 5 and i["type"] == "Libre" for i in j["items"]), j.get("items", [])[:1])
    st, j = a.get("Api/ejercicios.php", action="get", id="601")
    check("Detalle con músculos implicados", st == 200 and j["ejercicio"]["muName"] == "Cuádriceps", j)
    st, j = a.post("Api/ejercicios.php?action=record", {"ejercicioId": 601, "peso": 80, "fecha": str(today - dt.timedelta(days=7))})
    st2, j2 = a.post("Api/ejercicios.php?action=record", {"ejercicioId": 601, "peso": 85, "fecha": str(today)})
    check("Registrar marcas", st == 201 and st2 == 201, (st, j, st2, j2))
    st, j = a.get("Api/ejercicios.php", action="get", id="601")
    recs = j["ejercicio"]["records"]
    check("Marcas con progreso (+5 kg) y mejor marca", recs[0]["delta"] == 5 and j["ejercicio"]["best"] == 85, recs)
    st, j = a.post("Api/ejercicios.php?action=record", {"ejercicioId": 601, "peso": 90, "fecha": str(today + dt.timedelta(days=2))})
    check("Marca con fecha futura rechazada", st == 422 and "fecha" in j.get("fields", {}), (st, j))
    st, j = a.post("Api/ejercicios.php?action=record", {"ejercicioId": 601, "peso": -5, "fecha": str(today)})
    check("Marca con peso negativo rechazada", st == 422 and "peso" in j.get("fields", {}), (st, j))
    st, j = p.post("Api/ejercicios.php?action=record_delete", {"id": recs[0]["id"]})
    check("No se puede borrar la marca de otro usuario", st == 404, (st, j))

    # ------------------------------------------------------------------ CU-05
    print("\nCU-05 Rutinas")
    server_dia = a.get("Api/dashboard.php")[1]["today"]["dia"]  # día según la zona horaria del servidor
    st, j = a.post("Api/rutinas.php?action=save", {"name": "Vacía", "items": []})
    check("5.2 Guardar sin ejercicios → 'Añade al menos un ejercicio'", st == 422 and j.get("error") == "Añade al menos un ejercicio", (st, j))
    st, j = a.post("Api/rutinas.php?action=save", {"name": "Pierna", "dia": server_dia,
                   "items": [{"ej": 601, "sets": 4, "reps": 8}, {"ej": 702, "sets": 3, "reps": 12}]})
    wid, ver = j.get("id"), j.get("version")
    check("Crear rutina con ejercicios y día", st == 200 and wid and ver == 1, (st, j))
    st, j = a.post("Api/rutinas.php?action=save", {"name": "Dup", "items": [{"ej": 601, "sets": 3, "reps": 8}, {"ej": 601, "sets": 3, "reps": 8}]})
    check("Ejercicio repetido rechazado", st == 422, (st, j))
    st, j = a.post("Api/rutinas.php?action=save", {"name": "Mal", "items": [{"ej": 601, "sets": 0, "reps": 8}]})
    check("Series fuera de rango rechazadas", st == 422, (st, j))
    st, j = a.get("Api/rutinas.php", action="get", id=wid)
    check("Abrir rutina (ENTRAR A RUTINA)", st == 200 and [i["ej"] for i in j["work"]["items"]] == [601, 702] and j["work"]["canEdit"], j)
    st, j = a.post("Api/rutinas.php?action=save", {"id": wid, "version": ver, "name": "Pierna",
                   "dia": server_dia, "items": [{"ej": 702, "sets": 3, "reps": 12}, {"ej": 601, "sets": 5, "reps": 5}]})
    check("Reordenar (arrastrar y soltar) y guardar", st == 200 and j["version"] == ver + 1, (st, j))
    st, j = a.get("Api/rutinas.php", action="get", id=wid)
    check("El nuevo orden persiste", [i["ej"] for i in j["work"]["items"]] == [702, 601] and j["work"]["items"][1]["sets"] == 5, j)
    st, j = a.post("Api/rutinas.php?action=save", {"id": wid, "version": ver, "name": "Pierna (viejo)", "items": [{"ej": 601, "sets": 1, "reps": 1}]})
    check("5.3 Guardado con versión vieja (colisión) → 409", st == 409 and j.get("code") == "conflict"
          and "modificada por otro usuario" in j.get("error", ""), (st, j))
    st, j = a.get("Api/dashboard.php")
    check("Inicio muestra la rutina de hoy y deja de ser 'nuevo'", j.get("isNew") is False and j["todayWork"] and j["todayWork"]["id"] == wid, j)

    # Vínculo con profesor
    st, j = a.post("Api/rutinas.php?action=link", {"code": "ZZZZZZZZ"})
    check("5.1 Código de profesor inválido → 'Código no encontrado'", st == 404 and j.get("error") == "Código no encontrado", (st, j))
    st, j = p.get("Api/rutinas.php", action="get", id=wid)
    check("Profesor NO vinculado no puede ver la rutina", st == 404, (st, j))
    st, j = a.post("Api/rutinas.php?action=link", {"code": code.lower()})
    check("Vincular con código válido", st == 200 and j["profesor"]["name"] == "Profe Test", (st, j))
    st, j = p.post("Api/rutinas.php?action=link", {"code": code})
    check("Un profesor no puede vincularse a otro", st == 403, (st, j))
    st, j = p.get("Api/rutinas.php", action="alumnos")
    al = [x for x in j.get("alumnos", []) if x["email"] == a_email]
    check("RUTINAS ALUMNOS lista al alumno y sus rutinas", st == 200 and al and al[0]["works"][0]["id"] == wid, j)
    st, j = a.get("Api/rutinas.php", action="alumnos")
    check("Un alumno no accede a RUTINAS ALUMNOS", st == 403, (st, j))
    st, w = p.get("Api/rutinas.php", action="get", id=wid)
    st, j = p.post("Api/rutinas.php?action=save", {"id": wid, "version": w["work"]["version"], "name": "Pierna (profe)",
                   "items": [{"ej": 601, "sets": 4, "reps": 6}]})
    check("El profesor edita la rutina del alumno", st == 200, (st, j))
    st, j = p.post("Api/rutinas.php?action=save", {"name": "Para alumna", "ownerId": al[0]["id"], "items": [{"ej": 101, "sets": 3, "reps": 10}]})
    st2, j2 = a.get("Api/rutinas.php", action="list")
    check("CREAR RUTINA del profesor para el alumno", st == 200 and any(x["name"] == "Para alumna" for x in j2["mine"]), (st, j))
    otro = Client()
    register(otro, "usuario", "Otro")
    st, j = otro.post("Api/rutinas.php?action=save", {"name": "X", "ownerId": al[0]["id"], "items": [{"ej": 101, "sets": 3, "reps": 10}]})
    check("Un usuario no crea rutinas para otros", st == 403, (st, j))

    # Compartir por código (función preexistente "Vincular")
    st, j = a.post("Api/rutinas.php?action=share", {"id": wid})
    share_code = j.get("code")
    check("Generar código para compartir", st == 200 and re.fullmatch(r"[A-Z2-9]{6}", share_code or ""), j)
    st, j = otro.post("Api/rutinas.php?action=share", {"id": wid})
    check("Ajeno no puede compartir la rutina", st == 403, (st, j))
    st, j = otro.post("Api/rutinas.php?action=redeem", {"code": share_code})
    check("Canjear código agrega la rutina compartida", st == 200 and j["work"]["id"] == wid, (st, j))
    st, j = otro.get("Api/rutinas.php", action="get", id=wid)
    check("Rutina compartida es de solo lectura", st == 200 and j["work"]["canEdit"] is False, j)
    st, j = otro.post("Api/rutinas.php?action=save", {"id": wid, "version": j["work"]["version"], "name": "Hack", "items": [{"ej": 101, "sets": 1, "reps": 1}]})
    check("Usuario con rutina compartida no puede guardarla", st == 403, (st, j))
    tercero = Client()
    register(tercero, "usuario", "Tercero")
    st, j = tercero.post("Api/rutinas.php?action=redeem", {"code": share_code})
    check("El código es de un solo uso", st == 404, (st, j))
    st, j = otro.post("Api/rutinas.php?action=delete", {"id": wid})
    check("Ajeno no puede borrar la rutina", st == 403, (st, j))
    r = otro.s.get(f"{BASE}/Api/rutinas.php", params={"action": "delete", "id": wid})
    check("Acciones de escritura por GET rechazadas (405)", r.status_code == 405, r.status_code)
    st, j = a.post("Api/rutinas.php?action=unlink", {})
    st2, j2 = p.get("Api/rutinas.php", action="get", id=wid)
    check("Desvincular quita el acceso del profesor", st == 200 and st2 == 404, (st2, j2))
    st, j = a.post("Api/rutinas.php?action=delete", {"id": wid})
    st2, _ = a.get("Api/rutinas.php", action="get", id=wid)
    check("El dueño borra su rutina", st == 200 and st2 == 404, (st, st2))

    # ------------------------------------------------------------------ CU-07
    print("\nCU-07 Ajustes")
    st, j = a.post("Api/ajustes.php?action=theme", {"theme": "oscuro"})
    html = a.s.get(f"{BASE}/Ajustes/Ajustes.php").text
    check("Modo oscuro persiste en la cuenta", st == 200 and 'data-theme="oscuro"' in html, st)
    st, j = a.post("Api/ajustes.php?action=password", {"current": "Incorrecta1", "password": "NuevaClave9", "password2": "NuevaClave9"})
    check("7.2 Contraseña actual errónea → bad_current", st == 422 and j.get("code") == "bad_current", (st, j))
    st, j = a.post("Api/ajustes.php?action=password", {"current": PASS, "password": "corta", "password2": "corta"})
    check("Nueva contraseña débil rechazada", st == 422 and j.get("code") == "weak_password", (st, j))
    st, j = a.post("Api/ajustes.php?action=password", {"current": PASS, "password": "NuevaClave9", "password2": "NuevaClave9"})
    nl = Client()
    st2, j2 = nl.post("Api/auth.php?action=login", {"user": a_email, "password": "NuevaClave9"})
    check("Cambio de contraseña efectivo", st == 200 and st2 == 200, (st, j, st2))

    st, j = a.get("Api/ajustes.php", action="subscription")
    check("Estado inicial sin suscripción (modo prueba)", st == 200 and j["subscription"] is None and j["mode"] == "test", j)
    exp = f"12/{(today.year + 2) % 100:02d}"
    st, j = a.post("Api/ajustes.php?action=subscribe", {"holder": "Ana", "number": "4242 4242 4242 4241", "exp": exp, "cvc": "123"})
    check("Tarjeta con número inválido (Luhn) rechazada", st == 422 and "number" in j.get("fields", {}), (st, j))
    st, j = a.post("Api/ajustes.php?action=subscribe", {"holder": "Ana", "number": "4242424242424242", "exp": "01/20", "cvc": "123"})
    check("Tarjeta vencida rechazada", st == 422 and "exp" in j.get("fields", {}), (st, j))
    st, j = a.post("Api/ajustes.php?action=subscribe", {"holder": "Ana", "number": "4000 0000 0000 0002", "exp": exp, "cvc": "123"})
    check("7.1 Tarjeta rechazada → 'Pago rechazado. Verifique los datos'", st == 402 and j.get("error") == "Pago rechazado. Verifique los datos", (st, j))
    st, j = a.post("Api/ajustes.php?action=subscribe", {"holder": "Ana", "number": "4242 4242 4242 4242", "exp": exp, "cvc": "123"})
    check("Pago aprobado emite recibo", st == 201 and j["receipt"]["reference"].startswith("MR-TEST-") and j["receipt"]["card"].endswith("4242"), (st, j))
    st, j = a.post("Api/ajustes.php?action=subscribe", {"holder": "Ana", "number": "4242 4242 4242 4242", "exp": exp, "cvc": "123"})
    check("No se puede contratar dos veces", st == 409, (st, j))
    st, j = a.get("Api/ajustes.php", action="subscription")
    check("Suscripción activa e historial (rechazo + aprobado)", j["subscription"] and len(j["payments"]) == 2, j)
    st, j = a.post("Api/ajustes.php?action=cancel", {})
    st2, j2 = a.get("Api/ajustes.php", action="subscription")
    check("Cancelar suscripción", st == 200 and j2["subscription"] is None, (st, j2))
    st, j = a.post("Api/ajustes.php?action=support", {"asunto": "", "mensaje": "corto"})
    check("Soporte valida asunto y mensaje", st == 422 and set(j.get("fields", {})) == {"asunto", "mensaje"}, (st, j))
    st, j = a.post("Api/ajustes.php?action=support", {"asunto": "Consulta", "mensaje": "¿Cómo exporto mis rutinas?"})
    check("Soporte registra la consulta", st == 201 and j.get("ticket"), (st, j))

    # ------------------------------------------------------------------ resumen
    ok = sum(1 for _, c, _ in results if c)
    print(f"\n{ok}/{len(results)} pruebas OK")
    return 0 if ok == len(results) else 1


if __name__ == "__main__":
    sys.exit(main())
