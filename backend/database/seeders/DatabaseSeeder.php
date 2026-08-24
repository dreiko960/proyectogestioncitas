<?php

namespace Database\Seeders;

use Illuminate\Database\Seeder;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Hash;

class DatabaseSeeder extends Seeder
{
    
    public function run(): void
    {
        $now = now();
        $password = Hash::make('Demo1234');

        
        
        
        
        
        $users = [
            ['id' => '00000000-0000-0000-0000-000000000001', 'name' => 'Julia Mamani',            'email' => 'julia.mamani@gmail.com',    'role' => 'paciente'],
            ['id' => '00000000-0000-0000-0000-000000000002', 'name' => 'Carlos Huamán',          'email' => 'carlos.huaman@gmail.com',   'role' => 'paciente'],
            ['id' => '00000000-0000-0000-0000-000000000003', 'name' => 'María Quispe',           'email' => 'maria.quispe@gmail.com',    'role' => 'paciente'],
            ['id' => '00000000-0000-0000-0000-000000000004', 'name' => 'José Palomino',          'email' => 'jose.palomino@gmail.com',   'role' => 'paciente'],
            ['id' => '00000000-0000-0000-0000-000000000005', 'name' => 'Elena Rojas',            'email' => 'elena.rojas@gmail.com',     'role' => 'paciente'],
            ['id' => '00000000-0000-0000-0000-000000000006', 'name' => 'Dra. Rosa Quispe',       'email' => 'rosa.quispe@cmas.com',      'role' => 'medico'],
            ['id' => '00000000-0000-0000-0000-000000000007', 'name' => 'Dr. Carlos Mendoza',     'email' => 'carlos.mendoza@cmas.com',   'role' => 'medico'],
            ['id' => '00000000-0000-0000-0000-000000000008', 'name' => 'Dra. Lucía Fernández',   'email' => 'lucia.fernandez@cmas.com',  'role' => 'medico'],
            ['id' => '00000000-0000-0000-0000-000000000009', 'name' => 'Dr. Jorge Salas',        'email' => 'jorge.salas@cmas.com',      'role' => 'medico'],
            ['id' => '00000000-0000-0000-0000-000000000010', 'name' => 'Dra. María Torres',      'email' => 'maria.torres@cmas.com',     'role' => 'medico'],
            ['id' => '00000000-0000-0000-0000-000000000011', 'name' => 'Dr. Pedro Ramírez',      'email' => 'pedro.ramirez@cmas.com',    'role' => 'medico'],
            ['id' => '00000000-0000-0000-0000-000000000012', 'name' => 'Dra. Ana Flores',        'email' => 'ana.flores@cmas.com',       'role' => 'medico'],
            ['id' => '00000000-0000-0000-0000-000000000013', 'name' => 'Dr. Luis Gutiérrez',     'email' => 'luis.gutierrez@cmas.com',   'role' => 'medico'],
            ['id' => '00000000-0000-0000-0000-000000000014', 'name' => 'Carmen Paredes',         'email' => 'carmen.paredes@cmas.com',   'role' => 'enfermera'],
            ['id' => '00000000-0000-0000-0000-000000000015', 'name' => 'Miguel Chávez',          'email' => 'miguel.chavez@cmas.com',    'role' => 'recepcionista'],
            ['id' => '00000000-0000-0000-0000-000000000016', 'name' => 'Diana Álvarez',          'email' => 'admin@cmas.com',            'role' => 'administrador'],
        ];

        foreach ($users as $u) {
            DB::table('usuarios')->insert([
                'id' => $u['id'],
                'name' => $u['name'],
                'email' => $u['email'],
                'password_hash' => $password,
                'role' => $u['role'],
                'active' => true,
                'last_login_at' => $now->subHours(random_int(2, 48)),
                'created_at' => $now->subMonths(random_int(1, 6)),
                'updated_at' => $now->subMonths(random_int(1, 6)),
            ]);
        }

        
        
        
        $patients = [
            ['id' => '10000000-0000-0000-0000-000000000001', 'user_id' => '00000000-0000-0000-0000-000000000001', 'dni' => '44231221', 'phone' => '966541203', 'dob' => '1992-03-14', 'address' => 'Jr. Los Álamos 245, Ayacucho'],
            ['id' => '10000000-0000-0000-0000-000000000002', 'user_id' => '00000000-0000-0000-0000-000000000002', 'dni' => '45322109', 'phone' => '983112456', 'dob' => '1988-11-02', 'address' => 'Av. Independencia 812, Ayacucho'],
            ['id' => '10000000-0000-0000-0000-000000000003', 'user_id' => '00000000-0000-0000-0000-000000000003', 'dni' => '46781254', 'phone' => '977554321', 'dob' => '2000-07-25', 'address' => 'Psj. San Martín 18, Ayacucho'],
            ['id' => '10000000-0000-0000-0000-000000000004', 'user_id' => '00000000-0000-0000-0000-000000000004', 'dni' => '41209873', 'phone' => '955221908', 'dob' => '1965-01-30', 'address' => 'Jr. Cusco 501, Ayacucho'],
            ['id' => '10000000-0000-0000-0000-000000000005', 'user_id' => '00000000-0000-0000-0000-000000000005', 'dni' => '48876512', 'phone' => '989441237', 'dob' => '1995-09-18', 'address' => 'Av. Venezuela 330, Ayacucho'],
        ];

        foreach ($patients as $p) {
            DB::table('pacientes')->insert([
                'id' => $p['id'],
                'user_id' => $p['user_id'],
                'dni' => $p['dni'],
                'phone' => $p['phone'],
                'dob' => $p['dob'],
                'address' => $p['address'],
                'consent_29733' => true,
                'consent_at' => $now->subMonths(6),
                'created_at' => $now->subMonths(6),
            ]);
        }

        
        
        
        $specialties = [
            ['id' => '20000000-0000-0000-0000-000000000001', 'code' => 'medicina',     'name' => 'Medicina General', 'icon' => 'stethoscope', 'price' => 60.00, 'desc' => 'Atención integral de salud del adulto'],
            ['id' => '20000000-0000-0000-0000-000000000002', 'code' => 'pediatria',    'name' => 'Pediatría',        'icon' => 'baby',         'price' => 70.00, 'desc' => 'Salud de niños y adolescentes'],
            ['id' => '20000000-0000-0000-0000-000000000003', 'code' => 'cardiologia',  'name' => 'Cardiología',      'icon' => 'heart',        'price' => 120.00, 'desc' => 'Enfermedades del corazón y sistema circulatorio'],
            ['id' => '20000000-0000-0000-0000-000000000004', 'code' => 'dermatologia', 'name' => 'Dermatología',     'icon' => 'eye',          'price' => 80.00, 'desc' => 'Enfermedades de la piel'],
            ['id' => '20000000-0000-0000-0000-000000000005', 'code' => 'ginecologia',  'name' => 'Ginecología',      'icon' => 'venus',        'price' => 90.00, 'desc' => 'Salud de la mujer'],
            ['id' => '20000000-0000-0000-0000-000000000006', 'code' => 'neurologia',   'name' => 'Neurología',       'icon' => 'brain',        'price' => 100.00, 'desc' => 'Trastornos del sistema nervioso'],
            ['id' => '20000000-0000-0000-0000-000000000007', 'code' => 'odontologia',  'name' => 'Odontología',      'icon' => 'tooth',        'price' => 50.00, 'desc' => 'Salud bucal'],
        ];

        foreach ($specialties as $s) {
            DB::table('especialidades')->insert([
                'id' => $s['id'],
                'code' => $s['code'],
                'name' => $s['name'],
                'icon' => $s['icon'],
                'price' => $s['price'],
                'desc' => $s['desc'],
                'active' => true,
            ]);
        }

        
        
        
        $consultorios = [
            ['id' => '30000000-0000-0000-0000-000000000001', 'nombre' => 'Consultorio 1', 'piso' => '1', 'area' => 'Medicina General y Odontología'],
            ['id' => '30000000-0000-0000-0000-000000000002', 'nombre' => 'Consultorio 2', 'piso' => '1', 'area' => 'Medicina General'],
            ['id' => '30000000-0000-0000-0000-000000000003', 'nombre' => 'Consultorio 3', 'piso' => '2', 'area' => 'Pediatría y Ginecología'],
            ['id' => '30000000-0000-0000-0000-000000000004', 'nombre' => 'Consultorio 4', 'piso' => '2', 'area' => 'Cardiología y Neurología'],
            ['id' => '30000000-0000-0000-0000-000000000005', 'nombre' => 'Consultorio 5', 'piso' => '3', 'area' => 'Dermatología'],
        ];

        foreach ($consultorios as $c) {
            DB::table('consultorios')->insert([
                'id' => $c['id'],
                'nombre' => $c['nombre'],
                'piso' => $c['piso'],
                'area' => $c['area'],
                'activo' => true,
            ]);
        }

        $consultorioSpecialties = [
            ['30000000-0000-0000-0000-000000000001', '20000000-0000-0000-0000-000000000001'],
            ['30000000-0000-0000-0000-000000000001', '20000000-0000-0000-0000-000000000007'],
            ['30000000-0000-0000-0000-000000000002', '20000000-0000-0000-0000-000000000001'],
            ['30000000-0000-0000-0000-000000000003', '20000000-0000-0000-0000-000000000002'],
            ['30000000-0000-0000-0000-000000000003', '20000000-0000-0000-0000-000000000005'],
            ['30000000-0000-0000-0000-000000000004', '20000000-0000-0000-0000-000000000003'],
            ['30000000-0000-0000-0000-000000000004', '20000000-0000-0000-0000-000000000006'],
            ['30000000-0000-0000-0000-000000000005', '20000000-0000-0000-0000-000000000004'],
        ];

        foreach ($consultorioSpecialties as [$consultorioId, $specialtyId]) {
            DB::table('consultorio_especialidad')->insert([
                'consultorio_id' => $consultorioId,
                'specialty_id' => $specialtyId,
            ]);
        }

        
        
        
        $doctors = [
            ['id' => '40000000-0000-0000-0000-000000000001', 'user_id' => '00000000-0000-0000-0000-000000000006', 'initials' => 'RQ', 'specialty_id' => '20000000-0000-0000-0000-000000000001', 'consultorio_id' => '30000000-0000-0000-0000-000000000002', 'phone' => '966000001', 'bio' => 'Médica general con 12 años de experiencia en atención primaria.', 'rating' => 4.80, 'rating_count' => 214, 'studies' => 'UNMSM', 'exp' => 12],
            ['id' => '40000000-0000-0000-0000-000000000002', 'user_id' => '00000000-0000-0000-0000-000000000007', 'initials' => 'CM', 'specialty_id' => '20000000-0000-0000-0000-000000000001', 'consultorio_id' => '30000000-0000-0000-0000-000000000001', 'phone' => '966000002', 'bio' => 'Médico general, especialista en medicina familiar.', 'rating' => 4.60, 'rating_count' => 167, 'studies' => 'UNSAAC', 'exp' => 9],
            ['id' => '40000000-0000-0000-0000-000000000003', 'user_id' => '00000000-0000-0000-0000-000000000008', 'initials' => 'LF', 'specialty_id' => '20000000-0000-0000-0000-000000000002', 'consultorio_id' => '30000000-0000-0000-0000-000000000003', 'phone' => '966000003', 'bio' => 'Pediatra, atención de niños desde recién nacidos.', 'rating' => 4.90, 'rating_count' => 305, 'studies' => 'UNMSM', 'exp' => 15],
            ['id' => '40000000-0000-0000-0000-000000000004', 'user_id' => '00000000-0000-0000-0000-000000000009', 'initials' => 'JS', 'specialty_id' => '20000000-0000-0000-0000-000000000003', 'consultorio_id' => '30000000-0000-0000-0000-000000000004', 'phone' => '966000004', 'bio' => 'Cardiólogo, prevención y tratamiento cardiovascular.', 'rating' => 4.70, 'rating_count' => 98, 'studies' => 'UPC', 'exp' => 11],
            ['id' => '40000000-0000-0000-0000-000000000005', 'user_id' => '00000000-0000-0000-0000-000000000010', 'initials' => 'MT', 'specialty_id' => '20000000-0000-0000-0000-000000000004', 'consultorio_id' => '30000000-0000-0000-0000-000000000005', 'phone' => '966000005', 'bio' => 'Dermatóloga, tratamiento de acné y enfermedades de la piel.', 'rating' => 4.75, 'rating_count' => 143, 'studies' => 'UNMSM', 'exp' => 8],
            ['id' => '40000000-0000-0000-0000-000000000006', 'user_id' => '00000000-0000-0000-0000-000000000011', 'initials' => 'PR', 'specialty_id' => '20000000-0000-0000-0000-000000000005', 'consultorio_id' => '30000000-0000-0000-0000-000000000003', 'phone' => '966000006', 'bio' => 'Ginecólogo-obstetra, salud integral de la mujer.', 'rating' => 4.55, 'rating_count' => 121, 'studies' => 'UNSAAC', 'exp' => 10],
            ['id' => '40000000-0000-0000-0000-000000000007', 'user_id' => '00000000-0000-0000-0000-000000000012', 'initials' => 'AF', 'specialty_id' => '20000000-0000-0000-0000-000000000006', 'consultorio_id' => '30000000-0000-0000-0000-000000000004', 'phone' => '966000007', 'bio' => 'Neuróloga, epilepsia y cefaleas crónicas.', 'rating' => 4.85, 'rating_count' => 88, 'studies' => 'UPCH', 'exp' => 13],
            ['id' => '40000000-0000-0000-0000-000000000008', 'user_id' => '00000000-0000-0000-0000-000000000013', 'initials' => 'LG', 'specialty_id' => '20000000-0000-0000-0000-000000000007', 'consultorio_id' => '30000000-0000-0000-0000-000000000001', 'phone' => '966000008', 'bio' => 'Odontólogo general, ortodoncia y estética dental.', 'rating' => 4.65, 'rating_count' => 176, 'studies' => 'UNSAAC', 'exp' => 7],
        ];

        foreach ($doctors as $d) {
            DB::table('doctores')->insert([
                'id' => $d['id'],
                'user_id' => $d['user_id'],
                'initials' => $d['initials'],
                'specialty_id' => $d['specialty_id'],
                'consultorio_id' => $d['consultorio_id'],
                'phone' => $d['phone'],
                'bio' => $d['bio'],
                'rating' => $d['rating'],
                'rating_count' => $d['rating_count'],
                'studies' => $d['studies'],
                'exp' => $d['exp'],
                'created_at' => $now->subMonths(6),
            ]);
        }

        
        
        
        foreach ($doctors as $d) {
            for ($day = 1; $day <= 5; $day++) {
                DB::table('horarios_doctores')->insert([
                    'doctor_id' => $d['id'],
                    'day_of_week' => $day,
                    'start_time' => '08:00:00',
                    'end_time' => '12:00:00',
                ]);
            }
        }

        
        
        
        
        DB::table('excepciones_doctores')->insert([
            'doctor_id' => '40000000-0000-0000-0000-000000000004',
            'date' => '2026-08-05',
            'reason' => 'Capacitación externa',
        ]);

        
        
        
        
        $appointments = [
            [
                'id' => '50000000-0000-0000-0000-000000000001', 'code' => 'C-1041', 'patient_id' => '10000000-0000-0000-0000-000000000001',
                'doctor_id' => '40000000-0000-0000-0000-000000000001', 'specialty_id' => '20000000-0000-0000-0000-000000000001',
                'date' => '2026-08-05', 'time' => '09:00:00', 'status' => 'en_espera_triaje', 'reason' => 'Control de presión arterial',
                'check_in_time' => '08:52:00', 'turno' => 'A-001', 'paid_type' => 'adelanto', 'created_at' => $now->subDays(4),
            ],
            [
                'id' => '50000000-0000-0000-0000-000000000002', 'code' => 'C-1042', 'patient_id' => '10000000-0000-0000-0000-000000000002',
                'doctor_id' => '40000000-0000-0000-0000-000000000002', 'specialty_id' => '20000000-0000-0000-0000-000000000001',
                'date' => '2026-08-05', 'time' => '09:30:00', 'status' => 'en_triaje', 'reason' => 'Dolor abdominal recurrente',
                'check_in_time' => '09:10:00', 'turno' => 'A-002', 'paid_type' => 'total', 'created_at' => $now->subDays(2),
            ],
            [
                'id' => '50000000-0000-0000-0000-000000000003', 'code' => 'C-1043', 'patient_id' => '10000000-0000-0000-0000-000000000003',
                'doctor_id' => '40000000-0000-0000-0000-000000000001', 'specialty_id' => '20000000-0000-0000-0000-000000000001',
                'date' => '2026-08-05', 'time' => '10:00:00', 'status' => 'triaje_completado', 'reason' => 'Chequeo general',
                'check_in_time' => '09:40:00', 'turno' => 'A-003', 'paid_type' => 'adelanto', 'created_at' => $now->subDays(5),
            ],
            [
                'id' => '50000000-0000-0000-0000-000000000004', 'code' => 'C-1021', 'patient_id' => '10000000-0000-0000-0000-000000000001',
                'doctor_id' => '40000000-0000-0000-0000-000000000001', 'specialty_id' => '20000000-0000-0000-0000-000000000001',
                'date' => '2026-07-20', 'time' => '09:00:00', 'status' => 'atendida', 'reason' => 'Fiebre y malestar general',
                'check_in_time' => '08:55:00', 'turno' => 'A-002', 'paid_type' => 'total', 'created_at' => $now->subDays(31),
            ],
            [
                'id' => '50000000-0000-0000-0000-000000000005', 'code' => 'C-1022', 'patient_id' => '10000000-0000-0000-0000-000000000002',
                'doctor_id' => '40000000-0000-0000-0000-000000000003', 'specialty_id' => '20000000-0000-0000-0000-000000000002',
                'date' => '2026-07-22', 'time' => '10:30:00', 'status' => 'atendida', 'reason' => 'Control de crecimiento (hijo)',
                'check_in_time' => '10:20:00', 'turno' => 'A-005', 'paid_type' => 'adelanto', 'created_at' => $now->subDays(29),
            ],
            [
                'id' => '50000000-0000-0000-0000-000000000006', 'code' => 'C-1044', 'patient_id' => '10000000-0000-0000-0000-000000000004',
                'doctor_id' => '40000000-0000-0000-0000-000000000005', 'specialty_id' => '20000000-0000-0000-0000-000000000004',
                'date' => '2026-08-10', 'time' => '11:00:00', 'status' => 'agendada', 'reason' => 'Consulta dermatológica',
                'check_in_time' => null, 'turno' => null, 'paid_type' => null, 'created_at' => $now->subDays(1),
            ],
        ];

        foreach ($appointments as $a) {
            DB::table('citas')->insert([
                'id' => $a['id'],
                'code' => $a['code'],
                'patient_id' => $a['patient_id'],
                'doctor_id' => $a['doctor_id'],
                'specialty_id' => $a['specialty_id'],
                'date' => $a['date'],
                'time' => $a['time'],
                'duration_min' => 30,
                'status' => $a['status'],
                'reason' => $a['reason'],
                'check_in_time' => $a['check_in_time'],
                'turno' => $a['turno'],
                'paid_type' => $a['paid_type'],
                'created_at' => $a['created_at'],
                'updated_at' => $a['created_at'],
            ]);
        }

        
        
        
        
        
        $triages = [
            ['appointment_id' => '50000000-0000-0000-0000-000000000003', 'pa' => '110/70', 'temp' => 36.5, 'fc' => 72, 'peso' => 55.2, 'talla' => 1.60, 'motivo' => 'Chequeo general anual', 'alergias' => null, 'at' => '2026-08-05 09:45:00'],
            ['appointment_id' => '50000000-0000-0000-0000-000000000004', 'pa' => '130/85', 'temp' => 38.2, 'fc' => 92, 'peso' => 64.0, 'talla' => 1.65, 'motivo' => 'Fiebre de 3 días', 'alergias' => null, 'at' => '2026-07-20 09:00:00'],
            ['appointment_id' => '50000000-0000-0000-0000-000000000005', 'pa' => null, 'temp' => 36.7, 'fc' => 84, 'peso' => 12.4, 'talla' => 0.94, 'motivo' => 'Control de crecimiento', 'alergias' => null, 'at' => '2026-07-22 10:25:00'],
        ];

        foreach ($triages as $t) {
            DB::table('triajes')->insert([
                'appointment_id' => $t['appointment_id'],
                'nurse_id' => '00000000-0000-0000-0000-000000000014',
                'pa' => $t['pa'],
                'temp' => $t['temp'],
                'fc' => $t['fc'],
                'peso' => $t['peso'],
                'talla' => $t['talla'],
                'motivo' => $t['motivo'],
                'alergias' => $t['alergias'],
                'at' => $t['at'],
            ]);
        }

        
        
        
        $diagnoses = [
            ['appointment_id' => '50000000-0000-0000-0000-000000000004', 'doctor_id' => '00000000-0000-0000-0000-000000000006', 'dx' => 'Infección respiratoria aguda', 'notes' => 'Antibiótico por 7 días, reposo e hidratación.', 'at' => '2026-07-20 09:35:00'],
            ['appointment_id' => '50000000-0000-0000-0000-000000000005', 'doctor_id' => '00000000-0000-0000-0000-000000000008', 'dx' => 'Control de crecimiento normal', 'notes' => 'Peso y talla dentro de percentiles esperados.', 'at' => '2026-07-22 11:00:00'],
        ];

        foreach ($diagnoses as $dx) {
            DB::table('diagnosticos')->insert([
                'appointment_id' => $dx['appointment_id'],
                'doctor_id' => $dx['doctor_id'],
                'dx' => $dx['dx'],
                'notes' => $dx['notes'],
                'at' => $dx['at'],
            ]);
        }

        
        
        
        $payments = [
            ['id' => '60000000-0000-0000-0000-000000000001', 'code' => 'P-0813', 'appointment_id' => '50000000-0000-0000-0000-000000000001', 'patient_id' => '10000000-0000-0000-0000-000000000001', 'amount' => 30.00, 'method' => 'tarjeta_pasarela', 'status' => 'pagado', 'paid_type' => 'adelanto', 'receipt_code' => 'R-2026-0813', 'verified_by' => null, 'gateway' => true, 'culqi_order_id' => 'order_demo_0813', 'culqi_charge_id' => 'charge_demo_0813', 'created_at' => $now->subDays(3)],
            ['id' => '60000000-0000-0000-0000-000000000002', 'code' => 'P-0814', 'appointment_id' => '50000000-0000-0000-0000-000000000002', 'patient_id' => '10000000-0000-0000-0000-000000000002', 'amount' => 60.00, 'method' => 'yape', 'status' => 'pagado', 'paid_type' => 'total', 'receipt_code' => 'R-2026-0814', 'verified_by' => '00000000-0000-0000-0000-000000000015', 'gateway' => false, 'culqi_order_id' => null, 'culqi_charge_id' => null, 'created_at' => $now->subDays(1)],
            ['id' => '60000000-0000-0000-0000-000000000003', 'code' => 'P-0815', 'appointment_id' => '50000000-0000-0000-0000-000000000003', 'patient_id' => '10000000-0000-0000-0000-000000000003', 'amount' => 30.00, 'method' => 'plin', 'status' => 'pagado', 'paid_type' => 'adelanto', 'receipt_code' => 'R-2026-0815', 'verified_by' => '00000000-0000-0000-0000-000000000015', 'gateway' => false, 'culqi_order_id' => null, 'culqi_charge_id' => null, 'created_at' => $now->subDays(4)],
            ['id' => '60000000-0000-0000-0000-000000000004', 'code' => 'P-0720', 'appointment_id' => '50000000-0000-0000-0000-000000000004', 'patient_id' => '10000000-0000-0000-0000-000000000001', 'amount' => 60.00, 'method' => 'efectivo', 'status' => 'pagado', 'paid_type' => 'total', 'receipt_code' => 'R-2026-0720', 'verified_by' => '00000000-0000-0000-0000-000000000015', 'gateway' => false, 'culqi_order_id' => null, 'culqi_charge_id' => null, 'created_at' => $now->subDays(30)],
            ['id' => '60000000-0000-0000-0000-000000000005', 'code' => 'P-0722', 'appointment_id' => '50000000-0000-0000-0000-000000000005', 'patient_id' => '10000000-0000-0000-0000-000000000002', 'amount' => 35.00, 'method' => 'transferencia', 'status' => 'pagado', 'paid_type' => 'adelanto', 'receipt_code' => 'R-2026-0722', 'verified_by' => '00000000-0000-0000-0000-000000000015', 'gateway' => false, 'culqi_order_id' => null, 'culqi_charge_id' => null, 'created_at' => $now->subDays(28)],
        ];

        foreach ($payments as $p) {
            DB::table('pagos')->insert([
                'id' => $p['id'],
                'code' => $p['code'],
                'appointment_id' => $p['appointment_id'],
                'patient_id' => $p['patient_id'],
                'amount' => $p['amount'],
                'method' => $p['method'],
                'status' => $p['status'],
                'paid_type' => $p['paid_type'],
                'receipt_code' => $p['receipt_code'],
                'verified_by' => $p['verified_by'],
                'gateway' => $p['gateway'],
                'culqi_order_id' => $p['culqi_order_id'],
                'culqi_charge_id' => $p['culqi_charge_id'],
                'created_at' => $p['created_at'],
            ]);
        }

        
        
        
        $waitlist = [
            ['id' => '70000000-0000-0000-0000-000000000001', 'code' => 'WL-008', 'patient_id' => '10000000-0000-0000-0000-000000000004', 'specialty_id' => '20000000-0000-0000-0000-000000000003', 'doctor_id' => '40000000-0000-0000-0000-000000000004', 'preferred' => 'Preferencia por las mañanas', 'position' => 1, 'status' => 'en_espera', 'enrolled_at' => $now->subDays(2)],
            ['id' => '70000000-0000-0000-0000-000000000002', 'code' => 'WL-009', 'patient_id' => '10000000-0000-0000-0000-000000000005', 'specialty_id' => '20000000-0000-0000-0000-000000000004', 'doctor_id' => '40000000-0000-0000-0000-000000000005', 'preferred' => null, 'position' => 1, 'status' => 'en_espera', 'enrolled_at' => $now->subDays(1)],
            ['id' => '70000000-0000-0000-0000-000000000003', 'code' => 'WL-010', 'patient_id' => '10000000-0000-0000-0000-000000000002', 'specialty_id' => '20000000-0000-0000-0000-000000000001', 'doctor_id' => '40000000-0000-0000-0000-000000000002', 'preferred' => null, 'position' => 1, 'status' => 'oferta', 'offer_date' => '2026-08-05', 'offer_time' => '11:00:00', 'offer_expires_at' => $now->addMinutes(15), 'enrolled_at' => $now->subHours(3)],
        ];

        foreach ($waitlist as $w) {
            DB::table('lista_espera')->insert([
                'id' => $w['id'],
                'code' => $w['code'],
                'patient_id' => $w['patient_id'],
                'specialty_id' => $w['specialty_id'],
                'doctor_id' => $w['doctor_id'],
                'preferred' => $w['preferred'],
                'position' => $w['position'],
                'status' => $w['status'],
                'offer_date' => $w['offer_date'] ?? null,
                'offer_time' => $w['offer_time'] ?? null,
                'offer_expires_at' => $w['offer_expires_at'] ?? null,
                'confirm_window_min' => 15,
                'enrolled_at' => $w['enrolled_at'],
            ]);
        }

        
        
        
        $audit = [
            ['email' => 'julia.mamani@gmail.com',   'action' => 'login',            'detail' => 'Inicio de sesión exitoso',              'sev' => 'info',    'route' => '/api/auth/login',                    'method' => 'POST',   'at' => $now->subHours(3)],
            ['email' => 'julia.mamani@gmail.com',   'action' => 'Cita creada',      'detail' => 'C-1041 · Dra. Rosa Quispe · 2026-08-05', 'sev' => 'info',    'route' => '/api/appointments',                  'method' => 'POST',   'at' => $now->subDays(3)],
            ['email' => 'miguel.chavez@cmas.com',   'action' => 'Pago verificado',  'detail' => 'P-0814 · Yape · S/. 60.00',             'sev' => 'info',    'route' => '/api/payments/verify',               'method' => 'POST',   'at' => $now->subDays(1)],
            ['email' => 'carmen.paredes@cmas.com',  'action' => 'Triaje iniciado',  'detail' => 'C-1042 · A-002',                        'sev' => 'info',    'route' => '/api/triage/50000000-0000-0000-0000-000000000002', 'method' => 'POST', 'at' => $now->subHours(1)],
            ['email' => 'jose.palomino@gmail.com',  'action' => 'Acceso denegado',  'detail' => 'Intento de ver historial sin relación clínica', 'sev' => 'danger', 'route' => '/api/appointments/patient/10000000-0000-0000-0000-000000000002', 'method' => 'GET', 'at' => $now->subHours(5)],
        ];

        foreach ($audit as $a) {
            DB::table('registro_auditoria')->insert([
                'at' => $a['at'],
                'email' => $a['email'],
                'action' => $a['action'],
                'detail' => $a['detail'],
                'sev' => $a['sev'],
                'route' => $a['route'],
                'method' => $a['method'],
            ]);
        }
    }
}
