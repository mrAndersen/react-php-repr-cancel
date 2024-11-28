async function test() {
    return await fetch("http://127.0.0.1:8081/test", {
        signal: AbortSignal.timeout(100)
    });
}

test().then((r) => console.log(r.status));
