'use strict';
// Run real HTTP requests against an isolated copy. Never writes project data.
const assert = require('node:assert/strict');
const fs = require('node:fs');
const os = require('node:os');
const path = require('node:path');
const net = require('node:net');
const { spawn } = require('node:child_process');
const { once } = require('node:events');

(async () => {
    const root = path.resolve(__dirname, '..');
    const temp = fs.mkdtempSync(path.join(os.tmpdir(), 'tgs-admin-test-'));
    let server;
    try {
        for (const dir of ['public', 'src', 'data']) fs.cpSync(path.join(root, dir), path.join(temp, dir), { recursive: true });
        const read = name => JSON.parse(fs.readFileSync(path.join(temp, 'data', name + '.json'), 'utf8'));
        const write = (name, data) => fs.writeFileSync(path.join(temp, 'data', name + '.json'), JSON.stringify(data));
        const students = read('students').slice(0, 2).map((s, i) => ({ ...s, id: String(i + 1), is_active: false, photo_drive_file_id: 'test-file' }));
        const teams = read('teams').slice(0, 2).map((t, i) => ({ ...t, id: 't0' + (i + 1), booth_no: 'original' }));
        assert.equal(students.length, 2); assert.equal(teams.length, 2);
        write('students', students); write('teams', teams);
        write('mapping', [{ student_id: '1', team_id: 't01', role: 'test' }]);
        write('featured_students', []); write('qr_codes', []);
        const portProbe = net.createServer(); portProbe.listen(0, '127.0.0.1'); await once(portProbe, 'listening');
        const port = portProbe.address().port; await new Promise(resolve => portProbe.close(resolve));
        server = spawn('php', ['-S', '127.0.0.1:' + port, '-t', path.join(temp, 'public')], {
            cwd: temp, windowsHide: true,
            env: { ...process.env, TGS_ADMIN_PASSWORD: 'test-admin', TGS_STUDENT_PASSWORD: 'test-student', TGS_GOOGLE_CREDENTIALS: '', TGS_MAINTENANCE: 'false' },
            stdio: ['ignore', 'pipe', 'pipe'],
        });
        let log = ''; server.stderr.on('data', data => { log += data; });
        const base = 'http://127.0.0.1:' + port;
        let ready = false;
        for (let i = 0; i < 80; i++) {
            try { await fetch(base + '/admin.php'); ready = true; break; } catch { await new Promise(resolve => setTimeout(resolve, 50)); }
        }
        assert.ok(ready, log);
        function client() {
            let cookie = '';
            return async (url, body) => {
                const response = await fetch(base + url, { redirect: 'manual', headers: { Cookie: cookie },
                    ...(body ? { method: 'POST', body: new URLSearchParams(body) } : {}) });
                const next = response.headers.get('set-cookie'); if (next) cookie = next.split(';')[0];
                return { status: response.status, location: response.headers.get('location') || '', text: await response.text() };
            };
        }
        const student = client(), admin = client(), anonymous = client();
        const nameClient = client();
        for (const storedId of ['1', '01']) {
            write('students', [{ ...students[0], id: storedId }, students[1]]);
            for (const inputId of ['1', '01', '001']) {
                assert.equal((await nameClient('/admin.php', { login_id: inputId, password: 'test-student' })).status, 302);
                assert.ok((await nameClient('/admin.php')).text.includes('name="original_id" value="' + storedId + '"'));
                await nameClient('/admin.php', { logout: '1' });
            }
        }
        write('students', [{ ...students[0], id: '1' }, { ...students[1], id: '01' }]);
        assert.equal((await nameClient('/admin.php', { login_id: '001', password: 'test-student' })).status, 200);
        const namedStudents = students.map((s, i) => ({ ...s, name: i === 0 ? '山田 太郎' : '佐藤 花子' }));
        write('students', namedStudents);
        for (const name of ['山田太郎', '山田　太郎', ' 山田 太郎 ']) {
            assert.equal((await nameClient('/admin.php', { login_id: name, password: 'test-student' })).status, 302);
            const own = await nameClient('/admin.php');
            assert.match(own.text, /name="original_id" value="1"/);
            await nameClient('/admin.php', { logout: '1' });
        }
        write('students', namedStudents.map(s => ({ ...s, name: '山田 太郎' })));
        assert.equal((await nameClient('/admin.php', { login_id: '山田太郎', password: 'test-student' })).status, 200);
        assert.equal((await nameClient('/admin.php', { login_id: '1', password: 'test-student' })).status, 302);
        await nameClient('/admin.php', { logout: '1' });
        write('students', students);
        const forbidden = response => assert.match(response.location, /error\.php\?status=403/);
        const token = response => { const match = response.text.match(/name="csrf_token" value="([a-f0-9]+)"/); assert.ok(match); return match[1]; };
        assert.match((await anonymous('/teams_admin.php')).location, /admin\.php/);
        const login = await anonymous('/admin.php');
        assert.match(login.text, /type="text" name="login_id"/);
        assert.doesNotMatch(login.text, /<select|name="login_role"/);
        assert.equal((await anonymous('/admin.php', { password: 'test-admin' })).status, 200);
        assert.equal((await anonymous('/admin.php', { login_id: 'admin', password: 'test-student' })).status, 200);
        assert.equal((await student('/admin.php', { login_id: 'missing', password: 'test-student' })).status, 200);
        assert.equal((await student('/admin.php', { login_id: '1', password: 'wrong' })).status, 200);
        assert.equal((await student('/admin.php', { login_id: '1', password: 'test-admin' })).status, 200);
        assert.equal((await student('/admin.php', { login_id: '1', password: 'test-student' })).status, 302);
        const profile = await student('/admin.php'); const csrf = token(profile);
        assert.match(profile.text, /自分のプロフィール/);
        assert.doesNotMatch(profile.text, /新しい学生を追加|name="is_active"|section=featured|admin\.php\?id=2/);
        const work = await student('/teams_admin.php?id=t01');
        assert.match(work.text, /name="save_team"/);
        assert.doesNotMatch(work.text, /name="save_members"|name="booth_no"|新しい作品を追加|teams_admin\.php\?id=t02/);
        const before = read('students');
        for (const url of ['/admin.php?id=2', '/admin.php?new=1', '/admin.php?section=featured', '/teams_admin.php?id=t02', '/teams_admin.php?new=1', '/exhibited_admin.php', '/mapping_admin.php?team_id=t01']) forbidden(await student(url));
        forbidden(await student('/admin.php', { save_student: '1', original_id: '2', mode: 'edit', csrf_token: csrf }));
        forbidden(await student('/teams_admin.php?id=t01', { save_members: '1', id: 't01', csrf_token: csrf }));
        forbidden(await student('/teams_admin.php', { save_team: '1', id: 't02', csrf_token: csrf }));
        forbidden(await student('/admin.php', { save_student: '1', original_id: '1', mode: 'new', csrf_token: csrf }));
        assert.deepEqual(read('students'), before);
        const fields = { ...students[0], original_id: '1', mode: 'edit', save_student: '1', csrf_token: csrf, headline: 'edited by student', is_active: '1', 'role[]': 'プログラマー' };
        forbidden(await student('/admin.php', { ...fields, csrf_token: 'invalid' }));
        const invalidProfile = await student('/admin.php', { ...fields, name: '' });
        assert.equal(invalidProfile.status, 200);
        assert.doesNotMatch(invalidProfile.text, /data-qr-value=/);
        const saved = await student('/admin.php', fields); assert.match(saved.location, /saved=1/);
        assert.equal(read('students')[0].headline, 'edited by student');
        assert.equal(read('students')[0].is_active, false);
        assert.deepEqual(read('students')[1], students[1]);
        const workFields = { ...teams[0], id: 't01', mode: 'edit', save_team: '1', csrf_token: csrf,
            development_start_date: '2026-01-01', development_end_date: '', game_name: 'edited work', booth_no: 'forged' };
        assert.match((await student('/teams_admin.php?id=t01', workFields)).location, /saved=1/);
        assert.equal(read('teams')[0].game_name, 'edited work'); assert.equal(read('teams')[0].booth_no, 'original');
        assert.deepEqual(read('teams')[1], teams[1]);
        assert.equal((await anonymous('/student_photo.php?id=1')).status, 404);
        assert.equal((await student('/student_photo.php?id=2')).status, 404);
        // Own private photo reaches the downloader (missing test credentials), rather than being rejected.
        assert.equal((await student('/student_photo.php?id=1')).status, 502);
        write('mapping', []);
        forbidden(await student('/teams_admin.php?id=t01', workFields));
        assert.match((await student('/teams_admin.php')).text, /参加作品は登録されていません/);
        assert.equal((await admin('/admin.php', { login_id: 'admin', password: 'test-admin' })).status, 302);
        for (const url of ['/admin.php?id=2', '/admin.php?new=1', '/admin.php?section=featured', '/teams_admin.php?id=t02', '/teams_admin.php?new=1', '/exhibited_admin.php']) assert.equal((await admin(url)).status, 200, url);
        const adminProfile = await admin('/admin.php?id=1');
        assert.match((await admin('/admin.php', { ...fields, csrf_token: token(adminProfile) })).location, /saved=1/);
        assert.equal(read('students')[0].is_active, true);
        const adminCsrf = token(adminProfile);
        assert.match((await admin('/teams_admin.php?id=t01', { save_members: '1', id: 't01', csrf_token: adminCsrf,
            'members[0][student_id]': '1', 'members[0][role]': 'programmer' })).location, /members_saved=1/);
        assert.equal(read('mapping')[0].student_id, '1');
        assert.match((await admin('/exhibited_admin.php', { id: 't01', booth_no: 'admin-booth', csrf_token: adminCsrf })).location, /saved=1/);
        assert.equal(read('teams')[0].booth_no, 'admin-booth');
        assert.match((await admin('/admin.php?section=featured', { save_featured: '1', student_id: '1', order: '1', focus: 'focus', teacher_comment: 'comment', csrf_token: adminCsrf })).location, /saved=1/);
        assert.equal(read('featured_students')[0].student_id, '1');
        write('mapping', [{ student_id: '1', team_id: 't01', role: 'test' }, { student_id: '1', team_id: 't02', role: 'test' }]);
        const multiple = await student('/teams_admin.php');
        assert.match(multiple.text, /teams_admin\.php\?id=t01/); assert.match(multiple.text, /teams_admin\.php\?id=t02/);
        await student('/admin.php', { logout: '1' });
        assert.match((await student('/teams_admin.php')).location, /admin\.php/);
        await student('/admin.php', { login_id: '1', password: 'test-student' });
        write('students', [students[1]]);
        assert.match((await student('/teams_admin.php')).location, /admin\.php/);
        console.log('PASS: student/admin HTTP login, scope, forged requests, saves, CSRF, private photos, membership removal, logout');
    } finally {
        if (server && server.exitCode === null) { server.kill(); await once(server, 'exit'); }
        assert.ok(path.resolve(temp).startsWith(path.resolve(os.tmpdir()) + path.sep));
        fs.rmSync(temp, { recursive: true, force: true });
    }
})().catch(error => { console.error(error); process.exitCode = 1; });
