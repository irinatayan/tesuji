let stoneAudio: HTMLAudioElement | null = null;

export function playStoneSound(): void {
  if (!stoneAudio) {
    stoneAudio = new Audio('/sounds/stone.mp3');
  }
  stoneAudio.currentTime = 0;
  stoneAudio.play().catch(() => {});
}
