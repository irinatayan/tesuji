<script lang="ts">
  import { Board } from '$lib/game/Board';
  import GoBoard from '$lib/board/GoBoard.svelte';

  const board = Board.empty(9).set({ x: 2, y: 2 }, 'black').set({ x: 3, y: 3 }, 'white');
</script>

<main>
  <h1>Tesuji</h1>
  <GoBoard size={9} {board} />
</main>
