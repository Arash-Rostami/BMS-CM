const scene = new THREE.Scene();
const camera = new THREE.PerspectiveCamera(75, window.innerWidth / window.innerHeight, 0.1, 1000);
const renderer = new THREE.WebGLRenderer({
    canvas: document.getElementById('canvas-bg'),
    alpha: true,
    antialias: true,
    powerPreference: "high-performance"
});

renderer.setSize(window.innerWidth, window.innerHeight);
renderer.setPixelRatio(Math.min(window.devicePixelRatio, 2));
camera.position.z = 5;

const particleCount = 2000;
const particles = new THREE.BufferGeometry();
const positions = new Float32Array(particleCount * 3);
const colors = new Float32Array(particleCount * 3);
const sizes = new Float32Array(particleCount);
const velocities = new Float32Array(particleCount * 3);
const phases = new Float32Array(particleCount);

const colorPalettes = [
    [[0.23, 0.51, 0.96], [0.06, 0.73, 0.51], [0.96, 0.62, 0.04], [0.66, 0.33, 0.97]],
    [[0.0, 0.7, 1.0], [0.5, 0.0, 1.0], [0.0, 0.9, 0.9], [0.3, 0.0, 0.8]],
    [[1.0, 0.3, 0.0], [1.0, 0.6, 0.0], [1.0, 0.2, 0.5], [0.9, 0.4, 0.1]],
    [[1.0, 0.0, 1.0], [0.0, 1.0, 1.0], [1.0, 1.0, 0.0], [0.5, 1.0, 0.0]]
];

let currentPalette = 0;
let paletteTransition = 0;

for (let i = 0; i < particleCount * 3; i += 3) {
    const theta = Math.random() * Math.PI * 2;
    const phi = Math.acos(2 * Math.random() - 1);
    const radius = 3 + Math.random() * 7;

    positions[i] = radius * Math.sin(phi) * Math.cos(theta);
    positions[i + 1] = radius * Math.sin(phi) * Math.sin(theta);
    positions[i + 2] = radius * Math.cos(phi);

    velocities[i] = (Math.random() - 0.5) * 0.002;
    velocities[i + 1] = (Math.random() - 0.5) * 0.002;
    velocities[i + 2] = (Math.random() - 0.5) * 0.002;

    phases[i / 3] = Math.random() * Math.PI * 2;
    sizes[i / 3] = Math.random() * 0.08 + 0.02;

    const palette = colorPalettes[currentPalette];
    const color = palette[Math.floor(Math.random() * palette.length)];
    colors[i] = color[0];
    colors[i + 1] = color[1];
    colors[i + 2] = color[2];
}

particles.setAttribute('position', new THREE.BufferAttribute(positions, 3));
particles.setAttribute('color', new THREE.BufferAttribute(colors, 3));
particles.setAttribute('size', new THREE.BufferAttribute(sizes, 1));

const particleMaterial = new THREE.ShaderMaterial({
    uniforms: {
        time: {value: 0},
        pixelRatio: {value: renderer.getPixelRatio()}
    },
    vertexShader: `
        attribute float size;
        varying vec3 vColor;
        uniform float time;
        uniform float pixelRatio;

        void main() {
            vColor = color;
            vec4 mvPosition = modelViewMatrix * vec4(position, 1.0);
            float perspectiveScale = 300.0 / -mvPosition.z;
            float wave1 = sin(time * 2.0 + position.x * 0.1) * 0.3 + 0.7;
            float wave2 = sin(time * 0.5 + position.y * 0.1) * 0.2 + 0.8;
            float wave3 = cos(time * 1.5 + position.z * 0.1) * 0.1 + 0.9;
            float combinedWave = wave1 * wave2 * wave3;
            float sizeVariation = sin(position.x * 0.5 + time) * cos(position.y * 0.5 + time) * 0.2 + 1.0;
            gl_PointSize = size * perspectiveScale * combinedWave * sizeVariation * pixelRatio;
            gl_Position = projectionMatrix * mvPosition;
        }
    `,
    fragmentShader: `
        varying vec3 vColor;
        void main() {
            vec2 center = gl_PointCoord - vec2(0.5);
            float dist = length(center);
            if (dist > 0.5) discard;
            float alpha = 1.0 - smoothstep(0.0, 0.5, dist);
            float glow = exp(-dist * 3.0) * 0.5;
            alpha += glow;
            gl_FragColor = vec4(vColor, alpha * 0.8);
        }
    `,
    transparent: true,
    blending: THREE.AdditiveBlending,
    depthWrite: false,
    vertexColors: true
});

const particleSystem = new THREE.Points(particles, particleMaterial);
scene.add(particleSystem);

const lightShapes = [];
const curveCount = 4;

for (let i = 0; i < curveCount; i++) {
    const points = [];
    const segments = 50;
    const offsetY = (i - curveCount / 2) * 1.5;

    for (let j = 0; j <= segments; j++) {
        const t = j / segments;
        const x = (t - 0.5) * 12;
        const y = offsetY + Math.sin(t * Math.PI * 2) * 0.8;
        const z = Math.cos(t * Math.PI * 3) * 0.5 - 3;
        points.push(new THREE.Vector3(x, y, z));
    }

    const curve = new THREE.CatmullRomCurve3(points);
    const tubeGeo = new THREE.TubeGeometry(curve, 100, 0.08, 8, false);
    const tubeMat = new THREE.MeshBasicMaterial({
        color: new THREE.Color().setHSL(0.55 + i * 0.1, 0.6, 0.5),
        transparent: true,
        opacity: 0.4
    });

    const tube = new THREE.Mesh(tubeGeo, tubeMat);
    tube.userData = {
        baseY: offsetY,
        offset: i * Math.PI / 2,
        speed: 0.5 + i * 0.2
    };
    lightShapes.push(tube);
    scene.add(tube);
}

const shapes = [];
const torusGeometry = new THREE.TorusGeometry(2.5, 0.4, 32, 128);
const torusMaterial = new THREE.MeshBasicMaterial({
    color: 0x4488ff,
    wireframe: true,
    transparent: true,
    opacity: 0.3
});
window.torusMaterial = torusMaterial;
const torus = new THREE.Mesh(torusGeometry, torusMaterial);
shapes.push({mesh: torus, rotationSpeed: {x: 0.001, y: 0.002}});
scene.add(torus);

const ringGeometry = new THREE.TorusGeometry(1.5, 0.2, 16, 64);
const ringMaterial = new THREE.MeshBasicMaterial({
    color: 0xff4488,
    wireframe: true,
    transparent: true,
    opacity: 0.4
});
window.ringMaterial = ringMaterial;
const ring = new THREE.Mesh(ringGeometry, ringMaterial);
shapes.push({mesh: ring, rotationSpeed: {x: -0.002, y: 0.003}});
scene.add(ring);

const sphereGeometry = new THREE.IcosahedronGeometry(4, 1);
const sphereMaterial = new THREE.MeshBasicMaterial({
    color: 0x88ff44,
    wireframe: true,
    transparent: true,
    opacity: 0.1
});
const sphere = new THREE.Mesh(sphereGeometry, sphereMaterial);
shapes.push({mesh: sphere, rotationSpeed: {x: 0.0005, y: 0.001}});
scene.add(sphere);

let isDarkMode = document.documentElement.classList.contains('dark');
const updateTheme = () => {
    isDarkMode = document.documentElement.classList.contains('dark');
    particleSystem.visible = isDarkMode;
    shapes.forEach(s => s.mesh.visible = isDarkMode);
    lightShapes.forEach(s => s.visible = !isDarkMode);
};
updateTheme();

const observer = new MutationObserver(updateTheme);
observer.observe(document.documentElement, {attributes: true, attributeFilter: ['class']});

let mouseX = 0;
let mouseY = 0;
let targetCameraX = 0;
let targetCameraY = 0;
let targetCameraZ = 5;

document.addEventListener('mousemove', (e) => {
    mouseX = (e.clientX / window.innerWidth - 0.5) * 2;
    mouseY = (e.clientY / window.innerHeight - 0.5) * 2;
    targetCameraX = mouseX * 1.5;
    targetCameraY = -mouseY * 1.5;
    targetCameraZ = 5 + Math.abs(mouseX) * 0.5 + Math.abs(mouseY) * 0.5;
});

document.addEventListener('click', (e) => {
    const clickX = (e.clientX / window.innerWidth - 0.5) * 10;
    const clickY = -(e.clientY / window.innerHeight - 0.5) * 10;
    const positions = particles.attributes.position.array;
    for (let i = 0; i < positions.length; i += 3) {
        const dx = positions[i] - clickX;
        const dy = positions[i + 1] - clickY;
        const distance = Math.sqrt(dx * dx + dy * dy);
        if (distance < 3) {
            const force = (3 - distance) / 3;
            velocities[i] += (dx / distance) * force * 0.05;
            velocities[i + 1] += (dy / distance) * force * 0.05;
            velocities[i + 2] += force * 0.02;
        }
    }
});

document.addEventListener('keydown', (e) => {
    if (e.key === 'c' || e.key === 'C') {
        currentPalette = (currentPalette + 1) % colorPalettes.length;
        paletteTransition = 1;
    }
});

function animate() {
    requestAnimationFrame(animate);
    const time = Date.now() * 0.001;

    camera.position.x += (targetCameraX - camera.position.x) * 0.05;
    camera.position.y += (targetCameraY - camera.position.y) * 0.05;
    camera.position.z += (targetCameraZ - camera.position.z) * 0.05;
    camera.lookAt(scene.position);

    particleMaterial.uniforms.time.value = time;

    const positions = particles.attributes.position.array;
    const colors = particles.attributes.color.array;

    for (let i = 0; i < positions.length; i += 3) {
        const index = i / 3;
        positions[i] += velocities[i];
        positions[i + 1] += velocities[i + 1];
        positions[i + 2] += velocities[i + 2];

        velocities[i] *= 0.99;
        velocities[i + 1] *= 0.99;
        velocities[i + 2] *= 0.99;

        const waveX = Math.sin(time * 0.5 + positions[i] * 0.1 + phases[index]) * 0.002;
        const waveY = Math.cos(time * 0.7 + positions[i + 1] * 0.1 + phases[index]) * 0.002;
        const waveZ = Math.sin(time * 0.3 + positions[i + 2] * 0.1 + phases[index]) * 0.001;

        positions[i] += waveX;
        positions[i + 1] += waveY;
        positions[i + 2] += waveZ;

        const dist = Math.sqrt(positions[i] ** 2 + positions[i + 1] ** 2 + positions[i + 2] ** 2);
        if (dist > 10) {
            const normal = {x: positions[i] / dist, y: positions[i + 1] / dist, z: positions[i + 2] / dist};
            positions[i] = normal.x * 9.9;
            positions[i + 1] = normal.y * 9.9;
            positions[i + 2] = normal.z * 9.9;

            const dot = velocities[i] * normal.x + velocities[i + 1] * normal.y + velocities[i + 2] * normal.z;
            velocities[i] -= 2 * dot * normal.x;
            velocities[i + 1] -= 2 * dot * normal.y;
            velocities[i + 2] -= 2 * dot * normal.z;
        }

        if (paletteTransition > 0) {
            const targetPalette = colorPalettes[currentPalette];
            const targetColor = targetPalette[index % targetPalette.length];
            colors[i] += (targetColor[0] - colors[i]) * 0.05;
            colors[i + 1] += (targetColor[1] - colors[i + 1]) * 0.05;
            colors[i + 2] += (targetColor[2] - colors[i + 2]) * 0.05;
        }
    }

    if (paletteTransition > 0) {
        paletteTransition -= 0.02;
        particles.attributes.color.needsUpdate = true;
    }

    particles.attributes.position.needsUpdate = true;

    shapes.forEach(shape => {
        shape.mesh.rotation.x += shape.rotationSpeed.x;
        shape.mesh.rotation.y += shape.rotationSpeed.y;
        const scale = 1 + Math.sin(time * 2) * 0.02;
        shape.mesh.scale.set(scale, scale, scale);
    });

    lightShapes.forEach((curve, idx) => {
        const d = curve.userData;
        curve.rotation.z = Math.sin(time * 0.3 + d.offset) * 0.15;
        curve.position.y = Math.sin(time * 0.4 + d.offset) * 0.3;
        curve.position.x = Math.cos(time * 0.2 + d.offset) * 0.4;
    });

    particleSystem.rotation.x += 0.0002;
    particleSystem.rotation.y += 0.0003;
    particleSystem.rotation.z += 0.0001;

    renderer.render(scene, camera);
}

window.addEventListener('resize', () => {
    camera.aspect = window.innerWidth / window.innerHeight;
    camera.updateProjectionMatrix();
    renderer.setSize(window.innerWidth, window.innerHeight);
    particleMaterial.uniforms.pixelRatio.value = renderer.getPixelRatio();
});

animate();
