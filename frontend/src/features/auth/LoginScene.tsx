import { useEffect, useRef } from 'react'
import * as THREE from 'three'

function addParcelLabel(group: THREE.Group, size: number) {
  const label = new THREE.Mesh(
    new THREE.BoxGeometry(size * 0.34, size * 0.24, 0.025),
    new THREE.MeshStandardMaterial({ color: 0xffffff, roughness: 0.65 }),
  )
  label.position.set(-size * 0.16, -size * 0.13, size / 2 + 0.014)
  group.add(label)

  const ink = new THREE.MeshStandardMaterial({ color: 0x61708c, roughness: 0.58 })
  ;[
    { width: 0.24, x: -0.16, y: -0.07 },
    { width: 0.24, x: -0.16, y: -0.13 },
    { width: 0.15, x: -0.205, y: -0.19 },
  ].forEach((line) => {
    const mark = new THREE.Mesh(
      new THREE.BoxGeometry(size * line.width, size * 0.018, 0.009),
      ink,
    )
    mark.position.set(size * line.x, size * line.y, size / 2 + 0.031)
    group.add(mark)
  })
}

function parcel(size: number, material: THREE.Material) {
  const group = new THREE.Group()
  const box = new THREE.Mesh(new THREE.BoxGeometry(size, size, size), material)
  box.castShadow = true
  box.receiveShadow = true
  group.add(box)

  const tapeMaterial = new THREE.MeshStandardMaterial({
    color: 0x16345f,
    roughness: 0.5,
  })
  const tapeTop = new THREE.Mesh(
    new THREE.BoxGeometry(size * 0.24, 0.025, size * 1.02),
    tapeMaterial,
  )
  tapeTop.position.y = size / 2 + 0.014
  group.add(tapeTop)

  addParcelLabel(group, size)
  return group
}

export function LoginScene() {
  const canvasRef = useRef<HTMLCanvasElement>(null)

  useEffect(() => {
    const canvas = canvasRef.current
    if (!canvas) return

    const renderer = new THREE.WebGLRenderer({ canvas, alpha: true, antialias: true })
    renderer.setPixelRatio(Math.min(window.devicePixelRatio, 2))
    renderer.shadowMap.enabled = true
    renderer.shadowMap.type = THREE.PCFShadowMap
    renderer.toneMapping = THREE.ACESFilmicToneMapping
    renderer.toneMappingExposure = 1.1

    const scene = new THREE.Scene()
    const camera = new THREE.PerspectiveCamera(36, 1, 0.1, 100)
    camera.position.set(0, 3.2, 10)

    const keyLight = new THREE.DirectionalLight(0xffffff, 3.4)
    keyLight.position.set(5, 7, 6)
    keyLight.castShadow = true
    keyLight.shadow.mapSize.set(1024, 1024)
    keyLight.shadow.radius = 8
    scene.add(keyLight)
    scene.add(new THREE.HemisphereLight(0xf3f7ff, 0x1b4380, 2.1))
    const rimLight = new THREE.PointLight(0x8fc0ff, 14, 12)
    rimLight.position.set(-4, 3, 1)
    scene.add(rimLight)

    const ground = new THREE.Mesh(
      new THREE.CircleGeometry(7, 64),
      new THREE.ShadowMaterial({ opacity: 0.18 }),
    )
    ground.rotation.x = -Math.PI / 2
    ground.position.y = -1.65
    ground.receiveShadow = true
    scene.add(ground)

    const pedestal = new THREE.Group()
    const baseMaterial = new THREE.MeshStandardMaterial({ color: 0xffffff, roughness: 0.76 })
    const base = new THREE.Mesh(new THREE.CylinderGeometry(2.15, 2.32, 0.46, 64), baseMaterial)
    base.position.y = -1.38
    base.castShadow = true
    base.receiveShadow = true
    pedestal.add(base)
    const disc = new THREE.Mesh(
      new THREE.CylinderGeometry(1.63, 1.63, 0.14, 64),
      new THREE.MeshStandardMaterial({ color: 0xe9eff8, roughness: 0.8 }),
    )
    disc.position.y = -1.08
    pedestal.add(disc)
    scene.add(pedestal)

    const stock = new THREE.Group()
    const carton = new THREE.MeshStandardMaterial({ color: 0x4f8ff7, roughness: 0.52 })
    const cartonLight = new THREE.MeshStandardMaterial({ color: 0x78adef, roughness: 0.5 })
    const size = 1.08
    const packages = [
      { position: [-0.7, -0.53, -0.48], rotation: 0.22, material: carton },
      { position: [0.7, -0.53, -0.38], rotation: -0.24, material: cartonLight },
      { position: [0.08, -0.53, 0.64], rotation: 0.36, material: carton },
    ]
    packages.forEach(({ position, rotation, material }) => {
      const item = parcel(size, material)
      item.position.set(position[0], position[1], position[2])
      item.rotation.y = rotation
      stock.add(item)
    })
    const topParcel = new THREE.Group()
    topParcel.position.set(0, 0.57, -0.04)
    topParcel.rotation.y = -0.1

    // A parcel needs four actual walls. A solid cube with a dark plane on top
    // leaves the flaps visually detached whenever they rotate away from it.
    const wallThickness = 0.045
    const half = size / 2
    const innerWall = new THREE.MeshStandardMaterial({
      color: 0x548bcf,
      roughness: 0.72,
      side: THREE.DoubleSide,
    })
    const addWall = (
      width: number,
      depth: number,
      x: number,
      z: number,
      innerFace: number,
    ) => {
      const wallMaterials = Array<THREE.Material>(6).fill(cartonLight)
      wallMaterials[innerFace] = innerWall
      const wall = new THREE.Mesh(
        new THREE.BoxGeometry(width, size, depth),
        wallMaterials,
      )
      wall.position.set(x, 0, z)
      wall.castShadow = true
      wall.receiveShadow = true
      topParcel.add(wall)
    }
    addWall(size, wallThickness, 0, half - wallThickness / 2, 5)
    addWall(size, wallThickness, 0, -half + wallThickness / 2, 4)
    addWall(wallThickness, size - 2 * wallThickness, -half + wallThickness / 2, 0, 0)
    addWall(wallThickness, size - 2 * wallThickness, half - wallThickness / 2, 0, 1)

    addParcelLabel(topParcel, size)

    const interior = new THREE.Mesh(
      new THREE.BoxGeometry(size - 2 * wallThickness, wallThickness, size - 2 * wallThickness),
      new THREE.MeshStandardMaterial({ color: 0x173b73, roughness: 0.85 }),
    )
    interior.position.y = half - 0.27
    interior.receiveShadow = true
    topParcel.add(interior)

    const glow = new THREE.PointLight(0xa9ccff, 0, 4)
    glow.position.set(0, size / 2 + 0.34, 0)
    topParcel.add(glow)

    const flapThickness = 0.028
    // The first closing pair sits inside the second, like a folded cardboard box.
    // Both pivot centers overlap the top edge of their wall, so no background
    // can show through at the fold as the flap moves.
    const innerFlapHeight = half - 0.022
    const outerFlapHeight = half + 0.002
    const flapMaterial = new THREE.MeshStandardMaterial({ color: 0x69a2ec, roughness: 0.52 })
    const makeFlap = (
      width: number,
      depth: number,
      hingePosition: [number, number, number],
      flapPosition: [number, number, number],
    ) => {
      const hinge = new THREE.Group()
      hinge.position.set(...hingePosition)
      const flap = new THREE.Mesh(
        new THREE.BoxGeometry(width, flapThickness, depth),
        flapMaterial,
      )
      flap.position.set(...flapPosition)
      flap.castShadow = true
      hinge.add(flap)
      topParcel.add(hinge)
      return hinge
    }
    const frontFlap = makeFlap(size, size / 2, [0, outerFlapHeight, half], [0, 0, -size / 4])
    const backFlap = makeFlap(size, size / 2, [0, outerFlapHeight, -half], [0, 0, size / 4])
    const leftFlap = makeFlap(size / 2, size, [-half, innerFlapHeight, 0], [size / 4, 0, 0])
    const rightFlap = makeFlap(size / 2, size, [half, innerFlapHeight, 0], [-size / 4, 0, 0])

    const popMaterials = [
      new THREE.MeshStandardMaterial({ color: 0xffffff, roughness: 0.28 }),
      new THREE.MeshStandardMaterial({ color: 0x9fc2ff, roughness: 0.28 }),
      new THREE.MeshStandardMaterial({ color: 0x2f7bff, roughness: 0.28 }),
    ]
    const popData = [
      { x: -0.18, z: 0.2, size: 0.2, rise: 0.82 },
      { x: 0.17, z: -0.12, size: 0.17, rise: 1.03 },
      { x: 0.02, z: 0.02, size: 0.25, rise: 1.2 },
    ]
    const pops = popData.map((data, index) => {
      const item = new THREE.Mesh(
        new THREE.BoxGeometry(data.size, data.size, data.size),
        popMaterials[index],
      )
      item.position.set(data.x, size / 2 - 0.22, data.z)
      item.castShadow = true
      topParcel.add(item)
      return item
    })
    stock.add(topParcel)
    scene.add(stock)

    const ringMaterial = new THREE.MeshPhysicalMaterial({
      color: 0x2f7bff,
      roughness: 0.2,
      metalness: 0.12,
      clearcoat: 1,
      transmission: 0.18,
      thickness: 0.4,
    })
    const ring = new THREE.Mesh(new THREE.TorusGeometry(2.05, 0.07, 20, 96), ringMaterial)
    ring.rotation.x = Math.PI / 2.2
    ring.position.y = 0.08
    scene.add(ring)
    const outerRing = new THREE.Mesh(new THREE.TorusGeometry(2.48, 0.036, 16, 96), ringMaterial.clone())
    outerRing.material.color.set(0xa9caff)
    outerRing.rotation.set(Math.PI / 1.82, 0.42, 0)
    outerRing.position.y = 0.1
    scene.add(outerRing)

    const floating = new THREE.Group()
    const miniMaterials = [
      new THREE.MeshStandardMaterial({ color: 0x2f7bff, roughness: 0.3 }),
      new THREE.MeshStandardMaterial({ color: 0xffffff, roughness: 0.3 }),
      new THREE.MeshStandardMaterial({ color: 0x9fc2ff, roughness: 0.3 }),
    ]
    ;[
      [-2.8, 1.9, -0.8, 0.28],
      [2.9, 1.25, -0.9, 0.23],
      [-2.45, -0.15, 0.2, 0.18],
      [2.8, 2.65, -1.3, 0.32],
      [3.15, -0.35, 0.2, 0.16],
    ].forEach(([x, y, z, cubeSize], index) => {
      const mini = new THREE.Mesh(
        new THREE.BoxGeometry(cubeSize, cubeSize, cubeSize),
        miniMaterials[index % miniMaterials.length],
      )
      mini.position.set(x, y, z)
      mini.rotation.set(index * 0.4, index * 0.62, index * 0.25)
      mini.userData = { baseY: y, offset: index * 1.35 }
      floating.add(mini)
    })
    scene.add(floating)

    let pointerX = 0
    let pointerY = 0
    let currentX = 0
    let currentY = 0
    const onPointerMove = (event: PointerEvent) => {
      const bounds = canvas.getBoundingClientRect()
      pointerX = ((event.clientX - bounds.left) / bounds.width - 0.5) * 2
      pointerY = ((event.clientY - bounds.top) / bounds.height - 0.5) * 2
    }
    canvas.addEventListener('pointermove', onPointerMove)

    const resize = () => {
      const { width, height } = canvas.getBoundingClientRect()
      if (!width || !height) return
      renderer.setSize(width, height, false)
      camera.aspect = width / height
      camera.updateProjectionMatrix()
    }
    const observer = new ResizeObserver(resize)
    observer.observe(canvas)
    resize()

    const startedAt = performance.now()
    let frame = 0
    const render = (now: number) => {
      const elapsed = (now - startedAt) / 1000
      currentX += (pointerX - currentX) * 0.045
      currentY += (pointerY - currentY) * 0.045
      stock.rotation.y = elapsed * 0.24 + currentX * 0.55
      stock.rotation.x = currentY * 0.12
      stock.position.y = Math.sin(elapsed * 0.8) * 0.05

      // One continuous 16-second story: open, release the cubes, return, then close.
      const cycleTime = elapsed % 16
      const segment = (start: number, end: number) => {
        const progress = Math.min(Math.max((cycleTime - start) / (end - start), 0), 1)
        return progress * progress * (3 - 2 * progress)
      }
      const frontBackOpen = segment(0, 2) * (1 - segment(15, 16))
      const leftRightOpen = segment(2, 4) * (1 - segment(14, 15))
      frontFlap.rotation.x = frontBackOpen * 2.68
      backFlap.rotation.x = -frontBackOpen * 2.82
      leftFlap.rotation.z = leftRightOpen * 2.58
      rightFlap.rotation.z = -leftRightOpen * 2.88

      const cubesOutside = segment(4, 5.5) * (1 - segment(12.5, 14))
      glow.intensity = (frontBackOpen + leftRightOpen) * 0.7 + cubesOutside * 1.5
      pops.forEach((item, index) => {
        const data = popData[index]
        item.position.y = size / 2 - 0.22 + cubesOutside * data.rise + Math.sin(elapsed * 2 + index) * 0.05 * cubesOutside
        item.rotation.x = elapsed * (0.38 + index * 0.08) * cubesOutside
        item.rotation.y = elapsed * (0.52 + index * 0.1) * cubesOutside
      })
      topParcel.position.y = 0.57 + Math.sin(elapsed * 0.9) * 0.035

      ring.rotation.z = elapsed * 0.45
      outerRing.rotation.z = -elapsed * 0.3
      floating.children.forEach((item) => {
        item.position.y = item.userData.baseY + Math.sin(elapsed + item.userData.offset) * 0.16
        item.rotation.x += 0.006
        item.rotation.y += 0.009
      })
      camera.position.x += (currentX * 0.85 - camera.position.x) * 0.035
      camera.position.y += (3.2 - currentY * 0.55 - camera.position.y) * 0.035
      camera.lookAt(0, -0.05, 0)
      renderer.render(scene, camera)
      frame = requestAnimationFrame(render)
    }
    frame = requestAnimationFrame(render)

    return () => {
      cancelAnimationFrame(frame)
      observer.disconnect()
      canvas.removeEventListener('pointermove', onPointerMove)
      renderer.dispose()
      scene.traverse((object) => {
        if (!(object instanceof THREE.Mesh)) return
        object.geometry.dispose()
        const materials = Array.isArray(object.material) ? object.material : [object.material]
        materials.forEach((material) => material.dispose())
      })
    }
  }, [])

  return <canvas ref={canvasRef} className="login-scene" aria-label="Animasi stok Pandansari" />
}
